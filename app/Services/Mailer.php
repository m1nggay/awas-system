<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends HTML email through the Brevo transactional API (free tier, no SMTP
 * setup needed, real inbox delivery). Best-effort: never throws, so mail
 * trouble can't block registration, password resets or reviews.
 */
class Mailer
{
    public function send(string $toEmail, string $toName, string $subject, string $view, array $data = []): bool
    {
        $apiKey = (string)config('agas.brevo.api_key');
        if ($apiKey === '' || $toEmail === '') {
            Log::warning('Mailer: skipped — missing BREVO_API_KEY or recipient address.', ['subject' => $subject]);
            return false;
        }

        $payload = [
            'sender'      => ['name' => config('agas.brevo.from_name'), 'email' => config('agas.brevo.from_email')],
            'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
            'subject'     => $subject,
            'htmlContent' => view($view, $data)->render(),
        ];

        // One retry on a connection-level failure (slow TLS, DNS hiccup) so a
        // time-sensitive OTP isn't silently dropped. A real 4xx/5xx from Brevo
        // is not retried — retrying won't fix a bad request.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::withHeaders(['api-key' => $apiKey])
                    ->acceptJson()->connectTimeout(10)->timeout(20)
                    ->post('https://api.brevo.com/v3/smtp/email', $payload);

                if ($response->successful()) {
                    return true;
                }
                Log::error("Mailer: Brevo returned HTTP {$response->status()}", ['body' => $response->body()]);
                return false;
            } catch (ConnectionException $e) {
                Log::error("Mailer: Brevo connection failed (attempt $attempt/2): " . $e->getMessage());
            }
        }
        return false;
    }
}
