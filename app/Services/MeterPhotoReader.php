<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a photo of a water meter with the Claude API: the meter number printed
 * on the dial and the reading on the black number wheels. Meter readers don't
 * type readings — they take a photo, and these two values come from it.
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

    /** @return array{0: ?array{meter: string, reading: float}, 1: ?string} */
    public function read(string $bytes): array
    {
        $apiKey = config('agas.meter_reader.api_key');
        if (!$apiKey) {
            Log::warning('Meter photo reading skipped: no API key (METER_AI_API_KEY / CHATBOT_AI_API_KEY).');
            return [null, null];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

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
                            'type'       => 'object',
                            'properties' => [
                                'is_water_meter' => ['type' => 'boolean'],
                                'meter_number'   => ['type' => 'string'],
                                'reading'        => ['type' => 'string'],
                                'reading_clear'  => ['type' => 'boolean'],
                                'problem'        => ['type' => 'string'],
                            ],
                            'required'             => ['is_water_meter', 'meter_number', 'reading', 'reading_clear', 'problem'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'messages' => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]],
                        ['type' => 'text', 'text' => 'A meter reader took this photo of a household water meter to record its monthly reading. '
                            . 'The reading is entered from what you report, so report only what is clearly visible — never guess a digit. '
                            . "\n\nis_water_meter: true only if the photo shows a water meter's dial."
                            . "\nmeter_number: the meter's serial number printed on the dial face (usually 6 to 12 digits, e.g. 160400403). It is NOT the reading on the number wheels. Digits only, no spaces. Empty string if it is not fully readable."
                            . "\nreading: the cubic meters shown on the black-on-white number wheels (the row of digits next to the m³ mark). Digits only, keep them in order, leading zeros allowed. "
                            . 'Ignore the red pointers, red dials and any red digit wheels — those are fractions of a cubic meter. '
                            . 'If a wheel is turning between two digits, report the lower digit (the one it is leaving), except between 9 and 0 report 9. '
                            . 'Empty string if any black wheel cannot be read with certainty.'
                            . "\nreading_clear: true only if every black wheel digit is certain."
                            . "\nproblem: if something can't be read, a few words on why (e.g. dirty glass, glare, blurry, numbers cut off, condensation); otherwise an empty string."],
                    ],
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Meter photo reading failed: ' . $e->getMessage());
            return [null, null];
        }

        if (!$response->successful() || $response->json('stop_reason') === 'refusal') {
            Log::warning('Meter photo reading failed: HTTP ' . $response->status() . ' ' . $response->json('stop_reason', ''));
            return [null, null];
        }

        $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? '';
        $info = json_decode($text, true);
        if (!is_array($info)) {
            Log::warning('Meter photo reading returned unexpected output: ' . mb_substr($text, 0, 200));
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
}
