<?php

namespace App\Http\Controllers;

use App\Services\PayMongo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PayMongo webhook — the authoritative confirmation path; it works even if
 * the resident closes the tab before the redirect back finishes.
 *
 * The Paymongo-Signature header is verified against the RAW body first, and
 * even then the body's payment claims aren't trusted: the checkout session
 * is re-fetched from PayMongo with our secret key (PayMongo::verifyAndCredit).
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongo $paymongo)
    {
        if ((string)config('agas.paymongo.webhook_secret') === '') {
            Log::error('PayMongo webhook received but PAYMONGO_WEBHOOK_SECRET is not configured — rejecting.');
            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        $rawBody = $request->getContent();
        if (!$paymongo->verifyWebhookSignature($rawBody, $request->header('Paymongo-Signature'))) {
            Log::warning('PayMongo webhook signature verification failed.');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // The checkout session may be nested as data.attributes.data or data.data.
        $resource = $event['data']['attributes']['data'] ?? $event['data']['data'] ?? null;
        $sessionId = $resource['id'] ?? null;
        $resourceType = $resource['type'] ?? null;

        if (!$sessionId || ($resourceType && $resourceType !== 'checkout_session')) {
            // Acknowledge so PayMongo doesn't keep retrying something we'll never act on.
            return response()->json(['received' => true, 'skipped' => true]);
        }

        $payment = DB::table('payments')->where('payment_gateway_txn_id', $sessionId)->first();
        if (!$payment) {
            Log::warning("PayMongo webhook: no local payment row found for checkout session $sessionId");
            return response()->json(['received' => true, 'matched' => false]);
        }

        // Idempotent — safe even if the redirect or a QR-page poll already credited it.
        return response()->json(['received' => true, 'paid' => $paymongo->verifyAndCredit($payment)]);
    }
}
