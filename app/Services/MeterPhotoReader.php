<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a photo of a water meter with an AI model (Google Gemini, or the Claude API):
 * the meter number printed on the dial and the reading on the black number wheels.
 * Meter readers don't type readings — they take a photo, and these two values come from it.
 *
 * Three outcomes, like GcashReceiptReader:
 *   [['meter' => …, 'reading' => …], null]  read
 *   [null, message]                           the photo can't be read (retake it)
 *   [null, null]                              can't be checked right now (no API
 *                                             key, or the service is down): try later
 */
class MeterPhotoReader
{
    /** Why a photo couldn't be read → what the meter reader should do. */
    private const TIPS = 'Wipe the meter glass, avoid glare and shadows, hold the phone steady, and make sure the black numbers and the meter number are both in the photo.';

    private const PROMPT = 'A meter reader took this photo of a household water meter to record its monthly reading. '
        . 'The reading is entered from what you report, so report only what is clearly visible — never guess a digit. '
        . "\n\nis_water_meter: true only if the photo shows a water meter's dial."
        . "\nmeter_number: the meter's serial number printed on the dial face (usually 6 to 12 digits, e.g. 160400403). It is NOT the reading on the number wheels. Digits only, no spaces. Empty string if it is not fully readable."
        . "\nreading: the cubic meters shown on the black-on-white number wheels (the row of digits next to the m³ mark). Digits only, keep them in order, leading zeros allowed. "
        . 'Ignore the red pointers, red dials and any red digit wheels — those are fractions of a cubic meter. '
        . 'If a wheel is turning between two digits, report the lower digit (the one it is leaving), except between 9 and 0 report 9. '
        . 'Empty string if any black wheel cannot be read with certainty.'
        . "\nreading_clear: true only if every black wheel digit is certain."
        . "\nproblem: if something can't be read, a few words on why (e.g. dirty glass, glare, blurry, numbers cut off, condensation); otherwise an empty string.";

    /** The Gemini model found to work, when the configured one is gone. */
    private const GEMINI_MODEL_CACHE = 'meter-reader:gemini-model';

    private const FIELDS = ['is_water_meter' => 'boolean', 'meter_number' => 'string', 'reading' => 'string', 'reading_clear' => 'boolean', 'problem' => 'string'];

    /** @return array{0: ?array{meter: string, reading: float}, 1: ?string} */
    public function read(string $bytes): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        // Reading a photo can take longer than PHP's usual 30-second limit.
        @set_time_limit(120);

        $info = config('agas.meter_reader.provider') === 'gemini'
            ? $this->askGemini($bytes, $mime)
            : $this->askClaude($bytes, $mime);
        if ($info === null) {
            return [null, null];
        }

        $meter = preg_replace('/\D+/', '', (string)($info['meter_number'] ?? ''));
        $reading = preg_replace('/\D+/', '', (string)($info['reading'] ?? ''));
        $problem = trim((string)($info['problem'] ?? ''));
        $why = $problem !== '' ? ' (' . rtrim($problem, '.') . ')' : '';

        if (empty($info['is_water_meter'])) {
            return [null, 'This photo does not show a water meter. Take a photo of the meter dial.'];
        }
        if ($reading === '' || empty($info['reading_clear'])) {
            return [null, "The reading on the meter could not be read clearly$why. " . self::TIPS];
        }
        if ($meter === '') {
            return [null, "The meter number on the dial could not be read$why. " . self::TIPS];
        }
        return [['meter' => $meter, 'reading' => (float)(int)$reading], null];
    }

    /** Google Gemini (generateContent with a JSON answer). Null when it can't be asked right now. */
    private function askGemini(string $bytes, string $mime): ?array
    {
        $apiKey = config('agas.meter_reader.gemini_key');
        if (!$apiKey) {
            Log::warning('Meter photo reading skipped: no API key (GEMINI_API_KEY).');
            return null;
        }
        $model = Cache::get(self::GEMINI_MODEL_CACHE) ?: config('agas.meter_reader.gemini_model');

        $response = $this->postGemini($apiKey, $model, $bytes, $mime);
        // Google retires model names: when the model is gone (404), find one that the key can use and remember it.
        if ($response && $response->status() === 404 && ($found = $this->findGeminiModel($apiKey)) && $found !== $model) {
            Log::info("Meter photo reading: Gemini model $model not found, using $found.");
            Cache::put(self::GEMINI_MODEL_CACHE, $found, now()->addDays(7));
            $response = $this->postGemini($apiKey, $found, $bytes, $mime);
        }
        if (!$response) {
            return null;
        }
        if (!$response->successful()) {
            Log::warning('Meter photo reading failed (Gemini): HTTP ' . $response->status() . ' ' . mb_substr(preg_replace('/\s+/', ' ', $response->body()), 0, 300));
            return null;
        }
        $text = collect($response->json('candidates.0.content.parts', []))->pluck('text')->filter()->implode('');
        return $this->decode($text, 'Gemini (' . $response->json('candidates.0.finishReason', 'no answer') . ')');
    }

    private function postGemini(string $apiKey, string $model, string $bytes, string $mime): ?Response
    {
        try {
            return Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(90)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent", [
                    'contents' => [[
                        'parts' => [
                            ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                            ['text' => self::PROMPT],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature'      => 0,
                        'responseMimeType' => 'application/json',
                        'responseSchema'   => [
                            'type'       => 'OBJECT',
                            'properties' => collect(self::FIELDS)->map(fn ($t) => ['type' => strtoupper($t)])->all(),
                            'required'   => array_keys(self::FIELDS),
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Meter photo reading failed (Gemini): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * A Gemini "Flash" model this key can use: the always-current alias when it is listed,
     * otherwise the newest stable Flash model. Null when the list can't be fetched.
     */
    private function findGeminiModel(string $apiKey): ?string
    {
        try {
            $list = Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(20)
                ->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize' => 1000]);
        } catch (\Throwable) {
            return null;
        }
        $names = collect($list->json('models', []))
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => str_replace('models/', '', $m['name']))
            ->filter(fn ($n) => str_starts_with($n, 'gemini-') && str_contains($n, 'flash')
                && !preg_match('/lite|image|tts|audio|live|thinking|exp|preview|8b/', $n))
            ->values();
        if ($names->contains('gemini-flash-latest')) {
            return 'gemini-flash-latest';
        }
        return $names->sort(SORT_NATURAL)->last();
    }

    /** The Claude API (Messages with a JSON answer). Null when it can't be asked right now. */
    private function askClaude(string $bytes, string $mime): ?array
    {
        $apiKey = config('agas.meter_reader.api_key');
        if (!$apiKey) {
            Log::warning('Meter photo reading skipped: no API key (METER_AI_API_KEY / CHATBOT_AI_API_KEY).');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta'    => 'server-side-fallback-2026-07-01',
            ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
                'model'         => config('agas.meter_reader.model'),
                'max_tokens'    => 8000,
                'fallbacks'     => 'default',
                'output_config' => [
                    'effort' => 'medium',
                    'format' => [
                        'type'   => 'json_schema',
                        'schema' => [
                            'type'                 => 'object',
                            'properties'           => collect(self::FIELDS)->map(fn ($t) => ['type' => $t])->all(),
                            'required'             => array_keys(self::FIELDS),
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'messages' => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]],
                        ['type' => 'text', 'text' => self::PROMPT],
                    ],
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Meter photo reading failed: ' . $e->getMessage());
            return null;
        }

        if (!$response->successful() || $response->json('stop_reason') === 'refusal') {
            Log::warning('Meter photo reading failed: HTTP ' . $response->status() . ' ' . $response->json('stop_reason', '') . ' ' . mb_substr($response->body(), 0, 300));
            return null;
        }
        $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? '';
        return $this->decode($text, 'Claude');
    }

    private function decode(string $text, string $from): ?array
    {
        $info = json_decode($text, true);
        if (!is_array($info)) {
            Log::warning("Meter photo reading returned unexpected output from $from: " . mb_substr($text, 0, 200));
            return null;
        }
        return $info;
    }
}
