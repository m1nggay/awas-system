<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
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
    /** E-wallets residents can choose, as PayMongo names them => as shown on screen. */
    public const WALLETS = [
        'gcash'      => 'GCash',
        'paymaya'    => 'Maya',
    ];

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

        $send = function (?string $ip) use ($secretKey, $method, $path, $jsonBody) {
            $pending = Http::withBasicAuth($secretKey, '')->acceptJson()->connectTimeout(5)->timeout(20);
            if ($ip) {
                // Same host name and certificate check, just a specific PayMongo server.
                $pending = $pending->withOptions(['curl' => [CURLOPT_RESOLVE => ["api.paymongo.com:443:$ip"]]]);
            }
            return $jsonBody !== null
                ? $pending->send($method, 'https://api.paymongo.com' . $path, ['json' => $jsonBody])
                : $pending->send($method, 'https://api.paymongo.com' . $path);
        };

        // PayMongo answers from several servers. One of them can be unreachable from a
        // given network (the connection hangs), so on a failed connection the others are
        // tried, and the one that worked is used first for the next hour.
        $working = Cache::get('paymongo:server');
        $servers = array_values(array_unique(array_filter([$working, null, ...(gethostbynamel('api.paymongo.com') ?: [])],
            fn ($ip) => $ip !== false)));
        $response = null;
        foreach ($servers as $ip) {
            try {
                $response = $send($ip);
                if ($ip && $ip !== $working) {
                    Cache::put('paymongo:server', $ip, now()->addHour());
                }
                break;
            } catch (ConnectionException $e) {
                Log::warning("PayMongo request failed ($method $path" . ($ip ? " via $ip" : '') . '): ' . $e->getMessage());
                // A payment page is only re-requested if the first try never reached PayMongo.
                if ($method !== 'GET' && !preg_match('/SSL connection timeout|Connection timed out after|Failed to connect|Connection refused|Could not resolve/i', $e->getMessage())) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::error("PayMongo request failed ($method $path): " . $e->getMessage());
                return null;
            }
        }
        if (!$response) {
            Log::error("PayMongo request failed ($method $path): no PayMongo server could be reached.");
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
     * Direct-to-wallet payment (PayMongo Payment Intent): returns the intent ID
     * and the URL of the wallet's own payment screen (GCash, Maya, …), so the
     * resident skips PayMongo's checkout page.
     * @return array{id: string, url: string}|null
     */
    private function createWalletPayment(string $wallet, float $amount, string $description, string $returnUrl, string $reference): ?array
    {
        $ok = fn (?array $r) => $r && $r['http_code'] >= 200 && $r['http_code'] < 300;

        $intent = $this->request('POST', '/v1/payment_intents', ['data' => ['attributes' => [
            'amount'                 => (int)round($amount * 100),
            'currency'               => 'PHP',
            'payment_method_allowed' => [$wallet],
            'capture_type'           => 'automatic',
            'description'            => $description,
            'metadata'               => ['payment_reference' => $reference],
        ]]]);
        $intentId = $intent['data']['id'] ?? null;
        if (!$ok($intent) || !$intentId) {
            return null;
        }

        $method = $this->request('POST', '/v1/payment_methods', ['data' => ['attributes' => ['type' => $wallet]]]);
        $attached = $ok($method) ? $this->request('POST', '/v1/payment_intents/' . rawurlencode($intentId) . '/attach', ['data' => ['attributes' => [
            'payment_method' => $method['data']['id'],
            'return_url'     => $returnUrl,
        ]]]) : null;
        $walletUrl = $attached['data']['attributes']['next_action']['redirect']['url'] ?? null;
        if (!$ok($attached) || !$walletUrl) {
            Log::error("PayMongo: could not open the $wallet payment for intent $intentId.");
            $this->cancelPaymentIntent($intentId);
            return null;
        }
        return ['id' => $intentId, 'url' => $walletUrl];
    }

    public function retrievePaymentIntent(string $intentId): ?array
    {
        $result = $this->request('GET', '/v1/payment_intents/' . rawurlencode($intentId));
        if (!$result || $result['http_code'] < 200 || $result['http_code'] >= 300) {
            return null;
        }
        return $result['data'] ?? null;
    }

    public function cancelPaymentIntent(string $intentId): bool
    {
        $result = $this->request('POST', '/v1/payment_intents/' . rawurlencode($intentId) . '/cancel');
        return $result && $result['http_code'] >= 200 && $result['http_code'] < 300;
    }

    /**
     * The resident left the wallet without paying (cancelled, failed, or the
     * attempt expired) — PayMongo then waits for a new payment method.
     */
    public function walletPaymentFailed(object $payment): bool
    {
        $txn = (string)$payment->payment_gateway_txn_id;
        if (!str_starts_with($txn, 'pi_')) {
            return false;
        }
        $status = $this->retrievePaymentIntent($txn)['attributes']['status'] ?? '';
        return in_array($status, ['awaiting_payment_method', 'cancelled'], true);
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

        // pi_… = direct-to-wallet payment; cs_… = PayMongo checkout page (older payments).
        $txn = $payment->payment_gateway_txn_id;
        if (str_starts_with($txn, 'pi_')) {
            if (($this->retrievePaymentIntent($txn)['attributes']['status'] ?? '') !== 'succeeded') return false;
        } else {
            $session = $this->retrieveCheckoutSession($txn);
            if (!$session || !$this->isSessionPaid($session)) return false;
        }

        try {
            DB::transaction(function () use ($payment) {
                $updated = DB::table('payments')
                    ->where('payment_id', $payment->payment_id)
                    ->where('status', '!=', 'verified')
                    ->update(['status' => 'verified', 'verified_at' => now(),
                        'remarks' => 'Paid with ' . self::walletName($payment->wallet ?? null) . ' via PayMongo (auto-verified)']);
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
     * Saves a PayMongo payment as waiting, for the bill's full balance. The bill
     * row is locked so a double click can't start two payments for it.
     * @return array{0: ?object, 1: ?int, 2: ?string, 3: float, 4: ?string} [bill, payment ID, reference, balance, error]
     */
    private function reservePayment(int $billId, ?int $consumerId, string $wallet, string $channel, ?int $staffId): array
    {
        return DB::transaction(function () use ($billId, $consumerId, $wallet, $channel, $staffId) {
            $bill = DB::table('water_bills')->where('bill_id', $billId)
                ->when($consumerId, fn ($q) => $q->where('consumer_id', $consumerId))->lockForUpdate()->first();
            $balance = $bill ? max(0, round((float)$bill->total_amount - (float)$bill->amount_paid, 2)) : 0;
            if (!$bill || $bill->status === 'paid' || $balance <= 0) {
                return [null, null, null, 0, $bill ? 'This bill is already paid.' : 'Bill not found.'];
            }
            if (DB::table('payments')->where('bill_id', $billId)->where('status', 'pending')->exists()) {
                return [null, null, null, 0, 'A payment for this bill is already waiting for verification.'];
            }
            $ref = $this->billing->generatePaymentReference();
            $paymentId = DB::table('payments')->insertGetId([
                'payment_reference' => $ref,
                'bill_id'           => $billId,
                'consumer_id'       => $bill->consumer_id,
                'amount_paid'       => $balance,
                'payment_method'    => 'paymongo',
                'wallet'            => $wallet,
                'channel'           => $channel,
                'payment_date'      => now(),
                'status'            => 'pending',
                'received_by'       => $staffId,
                'remarks'           => self::walletName($wallet) . ' via PayMongo — waiting for PayMongo',
            ], 'payment_id');
            return [$bill, $paymentId, $ref, $balance, null];
        });
    }

    /** Display name of a PayMongo payment method (payments.wallet). */
    public static function walletName(?string $wallet): string
    {
        return self::WALLETS[$wallet] ?? ($wallet === 'qrph' ? 'GCash/Maya QR' : 'E-wallet');
    }

    /**
     * At the barangay: a QR Ph code for one bill's balance, shown on the
     * administrator's screen. The consumer scans it with GCash, Maya or any
     * bank app; the bill turns Paid automatically once PayMongo confirms.
     * @return array{0: ?array{payment_id: int, image: string, expires_at: ?string, test_url: ?string, amount: float}, 1: ?string}
     */
    public function startCounterQr(int $billId, int $staffId): array
    {
        [$bill, $paymentId, $ref, $balance, $error] = $this->reservePayment($billId, null, 'qrph', 'counter', $staffId);
        if ($error) {
            return [null, $error];
        }
        $meter = DB::table('consumers')->where('consumer_id', $bill->consumer_id)->value('meter_number');
        $period = billingPeriodLabel($bill->billing_period);

        $ok = fn (?array $r) => $r && $r['http_code'] >= 200 && $r['http_code'] < 300;
        $intent = $this->request('POST', '/v1/payment_intents', ['data' => ['attributes' => [
            'amount'                 => (int)round($balance * 100),
            'currency'               => 'PHP',
            'payment_method_allowed' => ['qrph'],
            'capture_type'           => 'automatic',
            'description'            => "AWAS water bill — $period (Meter $meter)",
            'metadata'               => ['payment_reference' => $ref],
        ]]]);
        $intentId = $intent['data']['id'] ?? null;
        $method = $ok($intent) && $intentId ? $this->request('POST', '/v1/payment_methods', ['data' => ['attributes' => ['type' => 'qrph']]]) : null;
        $attached = $ok($method) ? $this->request('POST', '/v1/payment_intents/' . rawurlencode($intentId) . '/attach', ['data' => ['attributes' => [
            'payment_method' => $method['data']['id'],
        ]]]) : null;
        $code = $attached['data']['attributes']['next_action']['code'] ?? null;
        if (!$ok($attached) || empty($code['image_url'])) {
            if ($intentId) {
                $this->cancelPaymentIntent($intentId);
            }
            DB::table('payments')->where('payment_id', $paymentId)->delete();
            return [null, 'The QR code could not be created right now. Please try again, or record a Cash payment.'];
        }

        DB::table('payments')->where('payment_id', $paymentId)->update(['payment_gateway_txn_id' => $intentId]);
        log_activity($staffId, 'payment_qr_start', "Showed a GCash/Maya QR for payment $ref (" . formatCurrency($balance) . ") — meter $meter, $period");
        return [[
            'payment_id' => $paymentId,
            'image'      => $code['image_url'],
            'expires_at' => $code['expires_at'] ?? null,
            // Test mode only: a page that simulates the consumer paying.
            'test_url'   => $code['test_url'] ?? null,
            'amount'     => $balance,
        ], null];
    }

    public function expireCheckoutSession(string $checkoutSessionId): bool
    {
        $result = $this->request('POST', '/v1/checkout_sessions/' . rawurlencode($checkoutSessionId) . '/expire');
        return $result && $result['http_code'] >= 200 && $result['http_code'] < 300;
    }

    /**
     * Resident chose an e-wallet: saves the payment as waiting for PayMongo and
     * returns the wallet's own payment screen (GCash, Maya, …). The bill turns
     * Paid automatically once PayMongo confirms (return from the wallet,
     * webhook, or a later check).
     * @return array{0: ?string, 1: ?string} [wallet URL, error]
     */
    public function startCheckout(object $consumer, int $billId, int $userId, string $wallet = 'gcash'): array
    {
        if (!isset(self::WALLETS[$wallet])) {
            return [null, 'Please choose an e-wallet.'];
        }
        $walletName = self::WALLETS[$wallet];

        [$bill, $paymentId, $ref, $balance, $error] = $this->reservePayment($billId, (int)$consumer->consumer_id, $wallet, 'online', null);
        if ($error) {
            return [null, $error];
        }
        $period = billingPeriodLabel($bill->billing_period);

        $walletPayment = $this->createWalletPayment($wallet, $balance,
            "AWAS water bill — $period (Meter {$consumer->meter_number})",
            route('resident.bill.paymongo.return', ['bill' => $billId, 'payment' => $paymentId]), $ref);
        if (!$walletPayment) {
            DB::table('payments')->where('payment_id', $paymentId)->delete();
            return [null, "$walletName is not available right now. Please try again, or choose another way to pay."];
        }

        DB::table('payments')->where('payment_id', $paymentId)->update(['payment_gateway_txn_id' => $walletPayment['id']]);
        log_activity($userId, 'payment_paymongo_start', "Started PayMongo $walletName payment $ref (" . formatCurrency($balance) . ") for the $period bill");
        return [$walletPayment['url'], null];
    }

    /**
     * An unfinished PayMongo payment: credits it if it was actually paid,
     * otherwise expires the checkout page (so it can't be paid later) and
     * removes the payment. Returns true if it turned out to be paid.
     */
    public function settleOrCancel(object $payment): bool
    {
        if ($this->verifyAndCredit($payment)) {
            return true;
        }
        if ($payment->status !== 'pending') {
            return false;
        }
        // Keep the row unless PayMongo confirms it can no longer be paid:
        // a late payment must still find it.
        $txn = (string)$payment->payment_gateway_txn_id;
        if (str_starts_with($txn, 'pi_')) {
            if (!$this->cancelPaymentIntent($txn) && ($this->retrievePaymentIntent($txn)['attributes']['status'] ?? '') !== 'cancelled') {
                return false;
            }
        } elseif ($txn !== '' && !$this->expireCheckoutSession($txn)) {
            $session = $this->retrieveCheckoutSession($txn);
            if (($session['attributes']['status'] ?? '') !== 'expired') {
                return false;
            }
        }
        DB::table('payments')->where('payment_id', $payment->payment_id)->where('status', 'pending')->delete();
        return false;
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
