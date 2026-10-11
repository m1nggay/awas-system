<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a GCash receipt screenshot with the Claude API and returns its
 * Ref. No. and amount, so nobody has to type them in. The payment checks
 * (Ref. No. never used before, amount equals the bill) are done afterwards
 * by PaymentService on what is read here.
 */
class GcashReceiptReader
{
    public function __construct(private ApplicationFiles $files)
    {
    }

    /**
     * Three outcomes:
     *   [receipt, null] — read: its Ref. No. and amount
     *   [null, error]   — read, but it is not a valid / clear GCash receipt
     *   [null, null]    — could not be checked (no API key, or the service is
     *                     down): the water office reads it when verifying
     * @return array{0: ?array{ref: string, amount: float}, 1: ?string}
     */
    public function read(string $receiptFile): array
    {
        $apiKey = config('agas.receipt_reader.api_key');
        if (!$apiKey) {
            return [null, null];
        }
        $bytes = $this->files->in('payment-receipts')->contents($receiptFile);
        if ($bytes === null) {
            return [null, 'Please attach the GCash receipt screenshot.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta'    => 'server-side-fallback-2026-07-01',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'         => config('agas.receipt_reader.model'),
                'max_tokens'    => 4000,
                'fallbacks'     => 'default',
                'output_config' => [
                    'effort' => 'low',
                    'format' => [
                        'type'   => 'json_schema',
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'is_gcash_receipt' => ['type' => 'boolean'],
                                'is_successful'    => ['type' => 'boolean'],
                                'reference_number' => ['type' => 'string'],
                                'amount'           => ['type' => 'number'],
                            ],
                            'required'             => ['is_gcash_receipt', 'is_successful', 'reference_number', 'amount'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'messages' => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]],
                        ['type' => 'text', 'text' => 'This image was uploaded as proof of a GCash payment for a water bill. '
                            . 'is_gcash_receipt: true only if it is a GCash app transaction receipt (not another app, a bank, or an unrelated picture). '
                            . 'is_successful: true only if the receipt shows a completed/successful payment. '
                            . 'reference_number: the "Ref. No." exactly as shown, digits only with spaces removed; empty string if it is not readable. '
                            . 'amount: the total amount paid in pesos as a number; 0 if it is not readable. '
                            . 'Report only what is visible — never guess digits.'],
                    ],
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('GCash receipt reading failed: ' . $e->getMessage());
            return [null, null];
        }

        if (!$response->successful() || $response->json('stop_reason') === 'refusal') {
            Log::warning('GCash receipt reading failed: HTTP ' . $response->status() . ' ' . $response->json('stop_reason', ''));
            return [null, null];
        }

        $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? '';
        $info = json_decode($text, true);
        if (!is_array($info)) {
            Log::warning('GCash receipt reading returned unexpected output: ' . mb_substr($text, 0, 200));
            return [null, null];
        }

        $ref = preg_replace('/\D+/', '', (string)($info['reference_number'] ?? ''));
        if (empty($info['is_gcash_receipt']) || empty($info['is_successful']) || $ref === '' || (float)($info['amount'] ?? 0) <= 0) {
            Log::info('GCash receipt not accepted: ' . json_encode($info));
            return [null, 'Screenshot not valid.'];
        }
        return [['ref' => $ref, 'amount' => round((float)$info['amount'], 2)], null];
    }
}
