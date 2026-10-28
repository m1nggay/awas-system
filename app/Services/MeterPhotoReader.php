<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a photo of a water meter with an AI model (Google Gemini, or the Claude API):
 * the meter number printed on the dial and the reading on the black number wheels.
 * Meter readers don't type readings — they take a photo, and these two values come from it.
 *
 * Gemini: a few fast models read the photo at the same time (about 2–3 seconds), and their
 * answers are combined wheel by wheel (majority), so one model misreading a digit doesn't
 * decide the reading. Only when none of them answers are the bigger Flash models asked, one by one.
 *
 * Three outcomes, like GcashReceiptReader:
 *   [['meter' => …, 'reading' => …, 'turning' => bool], null]  read (turning: a wheel was between digits)
 *   [null, message]                                              the photo can't be read (retake it)
 *   [null, null]                                                 can't be checked right now (no API
 *                                                                key, or the service is down): try later
 */
class MeterPhotoReader
{
    /** Why a photo couldn't be read → what the meter reader should do. */
    private const TIPS = 'Wipe the meter glass, avoid glare and shadows, hold the phone steady, and make sure the black numbers and the meter number are both in the photo.';

    private const PROMPT = 'A meter reader took this photo of a household water meter to record its monthly reading.'
        . "\n\nis_water_meter: true only if the photo shows a water meter's dial."
        . "\nmeter_number: the meter's serial number printed on the dial face (usually 6 to 12 digits, e.g. 160400403). It is NOT the number wheels. Digits only, no spaces. Empty string if it is not fully readable."
        . "\nwheels: the black-on-white number wheels next to the m³ mark (cubic meters), one entry per wheel from left to right. "
        . 'Ignore red wheels, red pointers and the small red dials — those are fractions of a cubic meter. '
        . "For each wheel, digit = the digit showing. A turning wheel shows parts of two digits: give the digit it is leaving (the lower one; between 9 and 0 give 9), and turning = true."
        . "\nreading_clear: false only if a black wheel is covered, cut off or impossible to make out. Dirt or a turning wheel alone is not a reason."
        . "\nproblem: if something can't be read, a few words on why (e.g. glare, blurry, numbers cut off, condensation); otherwise an empty string.";

    /** The fast Gemini models asked together (GEMINI_MODELS overrides). */
    private const GEMINI_FAST = ['gemini-flash-lite-latest', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite'];

    /** @return array{0: ?array{meter: string, reading: float, turning: bool}, 1: ?string} */
    public function read(string $bytes): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        // The slower fallback models can take longer than PHP's usual 30-second limit.
        @set_time_limit(120);

        $answers = config('agas.meter_reader.provider') === 'gemini'
            ? $this->askGemini($bytes, $mime)
            : array_filter([$this->askClaude($bytes, $mime)]);
        if (!$answers) {
            return [null, null];
        }
        $info = $this->combine($answers);

        $problem = trim((string)($info['problem'] ?? ''));
        $why = $problem !== '' ? ' (' . rtrim($problem, '.') . ')' : '';
        if (!$info['is_water_meter']) {
            return [null, 'This photo does not show a water meter. Take a photo of the meter dial.'];
        }
        if ($info['reading'] === '' || !$info['reading_clear']) {
            return [null, "The reading on the meter could not be read clearly$why. " . self::TIPS];
        }
        if ($info['meter'] === '') {
            return [null, "The meter number on the dial could not be read$why. " . self::TIPS];
        }
        return [['meter' => $info['meter'], 'reading' => (float)(int)$info['reading'], 'turning' => $info['turning']], null];
    }

    /**
     * One answer from several: the meter number most of them gave, and each wheel's digit by
     * majority (ties: the first model's digit). Answers with a different number of wheels than
     * most are left out. A wheel counts as turning when any answer said so.
     */
    private function combine(array $answers): array
    {
        $answers = array_values($answers);
        $wheels = array_map(fn ($a) => array_values(array_filter(
            array_map(fn ($w) => ['digit' => preg_replace('/\D+/', '', (string)($w['digit'] ?? '')), 'turning' => !empty($w['turning'])], $a['wheels'] ?? []),
            fn ($w) => strlen($w['digit']) === 1)), $answers);
        $majority = fn (array $values) => $values ? array_search(max($counts = array_count_values($values)), $counts, true) : '';

        $lengths = array_filter(array_map('count', $wheels));
        $length = $lengths ? (int)$majority(array_map('strval', $lengths)) : 0;
        $same = array_values(array_filter($wheels, fn ($w) => count($w) === $length));
        $reading = '';
        $turning = false;
        for ($i = 0; $i < $length; $i++) {
            $digits = array_map(fn ($w) => $w[$i]['digit'], $same);
            $counts = array_count_values($digits);
            $reading .= max($counts) > 1 ? (string)array_search(max($counts), $counts, true) : $digits[0];
            $turning = $turning || collect($same)->contains(fn ($w) => $w[$i]['turning']);
        }

        $meters = array_filter(array_map(fn ($a) => preg_replace('/\D+/', '', (string)($a['meter_number'] ?? '')), $answers));
        $yes = fn (string $key) => count(array_filter($answers, fn ($a) => !empty($a[$key]))) * 2 >= count($answers);
        return [
            'is_water_meter' => $yes('is_water_meter'),
            'reading_clear'  => $yes('reading_clear'),
            'meter'          => (string)$majority(array_values($meters)),
            'reading'        => $reading,
            'turning'        => $turning,
            'problem'        => collect($answers)->first(fn ($a) => empty($a['reading_clear']))['problem'] ?? '',
        ];
    }

    /** What both APIs are asked to answer (JSON schema; Gemini wants upper-case types). */
    private function schema(bool $gemini): array
    {
        $t = fn (string $type) => ['type' => $gemini ? strtoupper($type) : $type];
        $object = fn (array $props) => ['type' => $gemini ? 'OBJECT' : 'object', 'properties' => $props, 'required' => array_keys($props)]
            + ($gemini ? [] : ['additionalProperties' => false]);
        return $object([
            'is_water_meter' => $t('boolean'),
            'meter_number'   => $t('string'),
            'wheels'         => $t('array') + ['items' => $object(['digit' => $t('string'), 'turning' => $t('boolean')])],
            'reading_clear'  => $t('boolean'),
            'problem'        => $t('string'),
        ]);
    }

    /** Google Gemini. The answers that came back (empty when none could be asked right now). */
    private function askGemini(string $bytes, string $mime): array
    {
        $apiKey = config('agas.meter_reader.gemini_key');
        if (!$apiKey) {
            Log::warning('Meter photo reading skipped: no API key (GEMINI_API_KEY).');
            return [];
        }
        $body = [
            'contents'         => [['parts' => [
                ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                ['text' => self::PROMPT],
            ]]],
            'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json', 'responseSchema' => $this->schema(true)],
        ];
        $url = fn (string $model) => "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";

        // The fast models, all at once.
        $fast = config('agas.meter_reader.gemini_models') ?: self::GEMINI_FAST;
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($m) => $pool->as($m)->withHeaders(['x-goog-api-key' => $apiKey])->timeout(12)->post($url($m), $body), $fast));
        $answers = [];
        foreach ($responses as $model => $response) {
            if ($info = $this->geminiAnswer($model, $response)) {
                $answers[] = $info;
            }
        }
        if ($answers) {
            return $answers;
        }

        // None answered (busy or retired): the bigger Flash models, one by one.
        $slow = array_values(array_unique(array_merge([config('agas.meter_reader.gemini_model')], $this->geminiModels($apiKey))));
        foreach (array_slice($slow, 0, 3) as $model) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(30)->post($url($model), $body);
            } catch (\Throwable $e) {
                $response = $e;
            }
            if ($info = $this->geminiAnswer($model, $response)) {
                return [$info];
            }
            if ($response instanceof Response && !in_array($response->status(), [404, 429, 500, 503], true)) {
                return [];   // e.g. a wrong key: another model won't help
            }
        }
        return [];
    }

    /** The answer in one Gemini response, or null (the failure is logged). */
    private function geminiAnswer(string $model, $response): ?array
    {
        if (!$response instanceof Response) {
            Log::warning("Meter photo reading failed (Gemini $model): " . ($response instanceof \Throwable ? $response->getMessage() : 'no response'));
            return null;
        }
        if (!$response->successful()) {
            Log::warning("Meter photo reading failed (Gemini $model): HTTP " . $response->status() . ' ' . mb_substr(preg_replace('/\s+/', ' ', $response->body()), 0, 300));
            return null;
        }
        $text = collect($response->json('candidates.0.content.parts', []))->pluck('text')->filter()->implode('');
        return $this->decode($text, "Gemini $model (" . $response->json('candidates.0.finishReason', 'no answer') . ')');
    }

    /**
     * The stable Gemini "Flash" models this key can use, newest first (no lite, image, audio or
     * preview models). Kept for a day; empty when the list can't be fetched.
     */
    private function geminiModels(string $apiKey): array
    {
        return Cache::remember('meter-reader:gemini-models', now()->addDay(), function () use ($apiKey) {
            try {
                $list = Http::withHeaders(['x-goog-api-key' => $apiKey])->timeout(20)
                    ->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize' => 1000]);
            } catch (\Throwable) {
                return [];
            }
            return collect($list->json('models', []))
                ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
                ->map(fn ($m) => str_replace('models/', '', $m['name']))
                ->filter(fn ($n) => preg_match('/^gemini-\d+(\.\d+)?-flash$/', $n))
                ->sort(SORT_NATURAL)->reverse()->values()->all();
        });
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
                    'format' => ['type' => 'json_schema', 'schema' => $this->schema(false)],
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
