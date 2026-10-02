<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayMongo hosted Checkout Sessions (v2 to create, v1 to retrieve — that
 * split is PayMongo's own). Residents pay on PayMongo's page; this app only
 * stores PayMongo's reference IDs, never card numbers or GCash credentials.
 *
 * A session's "paid" state is never trusted from a redirect query string or
 * an unverified webhook body — those only say WHICH session to check; we
 * then ask PayMongo directly with our secret key before crediting a bill.
 */
class PayMongo
{
    public function __construct(private BillingService $billing)
    {
    }

    public function isConfigured(): bool
    {
        return (string)config('agas.paymongo.secret_key') !== '';
    }

    /** Authenticated call; returns the decoded body plus 'http_code', or null on hard failure. */
    private function request(string $method, string $path, ?array $jsonBody = null): ?array
    {
        $secretKey = (string)config('agas.paymongo.secret_key');
        if ($secretKey === '') {
            Log::error('PayMongo: PAYMONGO_SECRET_KEY is not configured.');
            return null;
        }

        try {
            $pending = Http::withBasicAuth($secretKey, '')->acceptJson()->timeout(15);
            $response = $jsonBody !== null
                ? $pending->send($method, 'https://api.paymongo.com' . $path, ['json' => $jsonBody])
                : $pending->send($method, 'https://api.paymongo.com' . $path);
        } catch (\Throwable $e) {
            Log::error("PayMongo request failed ($method $path): " . $e->getMessage());
            return null;
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            Log::error("PayMongo returned non-JSON ($method $path): " . $response->body());
            return null;
        }
        $decoded['http_code'] = $response->status();
        if (!$response->successful()) {
            Log::error("PayMongo API error ($method $path): " . ($decoded['errors'][0]['detail'] ?? 'HTTP ' . $response->status()));
        }
        return $decoded;
    }

    /** @return array{id: string, checkout_url: string}|null */
    public function createCheckoutSession(array $attributes): ?array
    {
        $result = $this->request('POST', '/v2/checkout_sessions', ['data' => ['attributes' => $attributes]]);
        if (!$result || $result['http_code'] < 200 || $result['http_code'] >= 300) {
            return null;
        }

        $id = $result['data']['id'] ?? null;
        $checkoutUrl = $result['data']['attributes']['checkout_url'] ?? null;
        if (!$id || !$checkoutUrl) {
            Log::error('PayMongo checkout session response missing id/checkout_url.');
            return null;
        }
        return ['id' => $id, 'checkout_url' => $checkoutUrl];
    }

    public function retrieveCheckoutSession(string $checkoutSessionId): ?array
    {
        $result = $this->request('GET', '/v1/checkout_sessions/' . rawurlencode($checkoutSessionId));
        if (!$result || $result['http_code'] < 200 || $result['http_code'] >= 300) {
            return null;
        }
        return $result['data'] ?? null;
    }

    /** At least one "paid" payment on the session — PayMongo's authoritative "did they pay" check. */
    public function isSessionPaid(array $checkoutSession): bool
    {
        foreach ($checkoutSession['attributes']['payments'] ?? [] as $payment) {
            if (($payment['attributes']['status'] ?? '') === 'paid') {
                return true;
            }
        }
        return false;
    }

    /**
     * Re-checks a local payments row against PayMongo and, if actually paid,
     * credits the bill exactly once. Shared by the browser redirect, the
     * webhook, and the "Scan to Pay" status poll. Returns true if the payment
     * is (now, or already was) verified.
     */
    public function verifyAndCredit(object $payment): bool
    {
        if ($payment->status === 'verified') return true;
        if (empty($payment->payment_gateway_txn_id)) return false;

        $session = $this->retrieveCheckoutSession($payment->payment_gateway_txn_id);
        if (!$session || !$this->isSessionPaid($session)) return false;

        try {
            DB::transaction(function () use ($payment) {
                $updated = DB::table('payments')
                    ->where('payment_id', $payment->payment_id)
                    ->where('status', '!=', 'verified')
                    ->update(['status' => 'verified', 'remarks' => 'Paid via PayMongo (auto-verified)']);
                if ($updated > 0) {
                    $this->billing->applyPaymentToBill((int)$payment->bill_id, (float)$payment->amount_paid);
                    log_activity(null, 'payment_paymongo_verified', "PayMongo payment {$payment->payment_reference} verified");
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::error('PayMongo crediting failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifies the Paymongo-Signature header ("t=<ts>,te=<test sig>,li=<live sig>")
     * against the raw body: HMAC-SHA256 of "{t}.{body}" with the webhook secret.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string)config('agas.paymongo.webhook_secret');
        if (!$signatureHeader || $secret === '') return false;

        $parts = explode(',', $signatureHeader);
        if (count($parts) < 3) return false;

        $timestamp = $testSig = $liveSig = null;
        foreach ($parts as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key === 't') $timestamp = $value;
            if ($key === 'te') $testSig = $value;
            if ($key === 'li') $liveSig = $value;
        }
        if (!$timestamp) return false;

        $signature = !empty($liveSig) ? $liveSig : $testSig;
        if (!$signature) return false;

        return hash_equals(hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret), $signature);
    }
}
