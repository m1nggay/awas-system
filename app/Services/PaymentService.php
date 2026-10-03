<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Water bill payments — two methods only:
 *
 *   At the barangay (recorded by an administrator): Cash or GCash QR.
 *     Recorded as Paid immediately — the staff member has the cash in hand,
 *     or has seen the GCash payment arrive and enters its reference number.
 *
 *   Online (submitted by the consumer): GCash QR only.
 *     Saved as "Pending Verification"; only an administrator's Verify turns
 *     it into Paid (and updates the bill). Reject keeps the bill unpaid.
 *
 * Every write locks the bill row, so double clicks or two people at once
 * can never create two payments for the same bill.
 */
class PaymentService
{
    public function __construct(private BillingService $billing)
    {
    }

    /** A GCash Ref. No. is exactly 13 digits (shown on the receipt as e.g. "1234 567 890123"). */
    public function isValidGcashReference(string $ref): bool
    {
        return preg_match('/^\d{13}$/', $ref) === 1;
    }

    public function normalizeReference(?string $ref): string
    {
        return preg_replace('/[\s-]+/', '', clean($ref));
    }

    /** A reference already attached to a pending or paid payment can't be used again. */
    public function referenceInUse(string $ref): bool
    {
        return DB::table('payments')->where('payment_gateway_txn_id', $ref)
            ->whereIn('status', ['pending', 'verified'])->exists();
    }

    /** The same screenshot (identical image) attached to another pending or paid payment. */
    public function screenshotAlreadyUsed(string $receiptFile): bool
    {
        $new = DB::table('stored_files')->where('name', $receiptFile)->first(['size', 'data']);
        if (!$new) {
            return false;
        }
        $used = DB::table('payments')->whereIn('status', ['pending', 'verified'])->whereNotNull('receipt_file')->pluck('receipt_file');
        return DB::table('stored_files')->where('folder', 'payment-receipts')->where('name', '!=', $receiptFile)
            ->whereIn('name', $used)->where('size', $new->size)->where('data', $new->data)->exists();
    }

    public function pendingPayment(int $billId): ?object
    {
        return DB::table('payments')->where('bill_id', $billId)->where('status', 'pending')->first();
    }

    public function balance(object $bill): float
    {
        return max(0, round((float)$bill->total_amount - (float)$bill->amount_paid, 2));
    }

    /**
     * Consumer clicked "I Have Paid" after paying through the GCash QR.
     * @return array{0: ?object, 1: ?string} [payment, error]
     */
    public function submitOnline(object $consumer, int $billId, ?string $receiptFile, int $userId): array
    {
        return DB::transaction(function () use ($consumer, $billId, $receiptFile, $userId) {
            $bill = DB::table('water_bills')->where('bill_id', $billId)->where('consumer_id', $consumer->consumer_id)->lockForUpdate()->first();

            $error = match (true) {
                !$bill => 'Bill not found.',
                $bill->status === 'paid' || $this->balance($bill) <= 0 => 'This bill is already paid.',
                $this->pendingPayment($billId) !== null => 'A payment for this bill is already waiting for verification. Please wait for the water office to verify it.',
                !$receiptFile => 'Please attach the screenshot of your GCash receipt.',
                $this->screenshotAlreadyUsed($receiptFile) => 'This screenshot was already submitted for another payment. Please attach the screenshot of THIS payment\'s GCash receipt.',
                default => null,
            };
            if ($error) {
                return [null, $error];
            }

            $amount = $this->balance($bill);
            $ref = $this->billing->generatePaymentReference();
            $paymentId = DB::table('payments')->insertGetId([
                'payment_reference'      => $ref,
                'bill_id'                => $billId,
                'consumer_id'            => $consumer->consumer_id,
                'amount_paid'            => $amount,
                'payment_method'         => 'gcash',
                'channel'                => 'online',
                'payment_gateway_txn_id' => null,
                'receipt_file'           => $receiptFile,
                'payment_date'           => now(),
                'status'                 => 'pending',
                'remarks'                => 'Online GCash QR payment — pending verification',
            ], 'payment_id');

            $period = billingPeriodLabel($bill->billing_period);
            foreach (DB::table('users')->where('role', 'admin')->where('status', 'active')->pluck('user_id') as $adminId) {
                $this->billing->createNotification((int)$adminId, $billId, 'GCash Payment Submitted',
                    "Meter {$consumer->meter_number} — {$consumer->full_name} submitted a GCash payment of " . formatCurrency($amount)
                        . " for the $period bill with a receipt screenshot. Please verify.");
            }
            log_activity($userId, 'payment_submit', "Submitted GCash QR payment $ref (" . formatCurrency($amount) . ") for the $period bill with a receipt screenshot");

            return [DB::table('payments')->where('payment_id', $paymentId)->first(), null];
        });
    }

    /**
     * Consumer paid in person; an administrator records it as Paid.
     * @return array{0: ?object, 1: ?string} [payment, error]
     */
    public function recordAtCounter(int $billId, string $method, float $amount, string $reference, ?string $remarks, int $staffId): array
    {
        return DB::transaction(function () use ($billId, $method, $amount, $reference, $remarks, $staffId) {
            $bill = DB::table('water_bills')->where('bill_id', $billId)->lockForUpdate()->first();
            $balance = $bill ? $this->balance($bill) : 0;

            $error = match (true) {
                !$bill => 'Selected bill not found.',
                !in_array($method, ['cash', 'gcash'], true) => 'Please choose Cash or GCash QR.',
                $bill->status === 'paid' || $balance <= 0 => 'This bill is already paid.',
                $this->pendingPayment($billId) !== null => 'This bill has an online GCash payment waiting for verification. Verify or reject it first.',
                $amount <= 0 => 'Payment amount must be greater than zero.',
                $amount > $balance + 0.009 => 'Amount is more than the balance of ' . formatCurrency($balance) . '.',
                $method === 'gcash' && $reference !== '' && !$this->isValidGcashReference($reference) => 'The GCash reference number must be the 13-digit Ref. No. on the consumer\'s GCash receipt (numbers only) — or leave it blank.',
                $method === 'gcash' && $reference !== '' && $this->referenceInUse($reference) => 'That GCash reference number was already recorded.',
                default => null,
            };
            if ($error) {
                return [null, $error];
            }

            $ref = $this->billing->generatePaymentReference();
            $paymentId = DB::table('payments')->insertGetId([
                'payment_reference'      => $ref,
                'bill_id'                => $billId,
                'consumer_id'            => $bill->consumer_id,
                'amount_paid'            => round($amount, 2),
                'payment_method'         => $method,
                'channel'                => 'counter',
                'payment_gateway_txn_id' => $method === 'gcash' ? $reference : null,
                'payment_date'           => now(),
                'status'                 => 'verified',
                'received_by'            => $staffId,
                'verified_at'            => now(),
                'remarks'                => $remarks ?: ($method === 'cash' ? 'Paid in cash at the barangay' : 'Paid via GCash QR at the barangay'),
            ], 'payment_id');
            $this->billing->applyPaymentToBill($billId, round($amount, 2));

            $meter = DB::table('consumers')->where('consumer_id', $bill->consumer_id)->value('meter_number');
            log_activity($staffId, 'payment_record', "Recorded " . paymentMethodLabel($method) . " payment $ref (" . formatCurrency($amount)
                . ") at the barangay for meter $meter, " . billingPeriodLabel($bill->billing_period));

            return [DB::table('payments')->where('payment_id', $paymentId)->first(), null];
        });
    }

    /** Administrator confirms an online GCash payment → Paid, and the bill is updated. */
    public function verify(int $paymentId, int $staffId): ?string
    {
        return DB::transaction(function () use ($paymentId, $staffId) {
            $payment = DB::table('payments')->where('payment_id', $paymentId)->lockForUpdate()->first();
            if (!$payment || $payment->status !== 'pending') {
                return 'This payment is no longer waiting for verification.';
            }
            DB::table('payments')->where('payment_id', $paymentId)->update([
                'status' => 'verified', 'received_by' => $staffId, 'verified_at' => now(), 'rejection_reason' => null,
            ]);
            $this->billing->applyPaymentToBill((int)$payment->bill_id, (float)$payment->amount_paid);
            log_activity($staffId, 'payment_verify', "Verified GCash payment {$payment->payment_reference} (" . formatCurrency($payment->amount_paid)
                . ", GCash Ref. {$payment->payment_gateway_txn_id})");
            return null;
        });
    }

    /** Administrator rejects an invalid / unverifiable payment; the bill stays unpaid. */
    public function reject(int $paymentId, int $staffId, string $reason): ?string
    {
        return DB::transaction(function () use ($paymentId, $staffId, $reason) {
            $payment = DB::table('payments')->where('payment_id', $paymentId)->lockForUpdate()->first();
            if (!$payment || $payment->status !== 'pending') {
                return 'This payment is no longer waiting for verification.';
            }
            DB::table('payments')->where('payment_id', $paymentId)->update([
                'status' => 'rejected', 'received_by' => $staffId, 'rejection_reason' => mb_substr($reason, 0, 255),
            ]);

            $bill = DB::table('water_bills')->where('bill_id', $payment->bill_id)->first();
            $userId = DB::table('consumers')->where('consumer_id', $payment->consumer_id)->value('user_id');
            if ($userId) {
                $this->billing->createNotification((int)$userId, (int)$payment->bill_id, 'GCash Payment Rejected',
                    'Your GCash payment of ' . formatCurrency($payment->amount_paid) . ' for the ' . billingPeriodLabel($bill->billing_period)
                        . " bill (Ref. {$payment->payment_gateway_txn_id}) was rejected: $reason. Please pay again or visit the barangay water office.");
            }
            log_activity($staffId, 'payment_reject', "Rejected GCash payment {$payment->payment_reference} (Ref. {$payment->payment_gateway_txn_id}): $reason");
            return null;
        });
    }
}
