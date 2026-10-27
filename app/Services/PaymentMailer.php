<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Email updates on a payment's status, with its details (reference, amount,
 * method, bill, balance):
 *
 *   Consumer: submitted (waiting for verification), received (Paid), rejected.
 *   Administrators: an online payment waiting for verification, and an online
 *   payment confirmed automatically by PayMongo.
 *
 * Sent after the page has been returned (and the payment saved), so a slow
 * mail server never delays a payment. Best-effort, like Mailer.
 */
class PaymentMailer
{
    private const SUBJECTS = [
        'submitted' => 'Payment submitted — waiting for verification',
        'received'  => 'Payment received — thank you',
        'rejected'  => 'Payment rejected',
    ];

    public function __construct(private Mailer $mailer)
    {
    }

    /** Emails the consumer who owns the payment. $status: submitted, received or rejected. */
    public function toConsumer(int $paymentId, string $status): void
    {
        app()->terminating(function () use ($paymentId, $status) {
            $details = $this->details($paymentId);
            $user = $details ? DB::table('users')->where('user_id', $details['user_id'])->where('status', 'active')->first(['full_name', 'email']) : null;
            if (!$user || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $this->mailer->send($user->email, $user->full_name, 'AWAS: ' . self::SUBJECTS[$status] . " ({$details['reference']})",
                'emails.payment-status', $details + [
                    'name'   => $user->full_name,
                    'status' => $status,
                    'admin'  => false,
                    'url'    => $status === 'received' ? route('payments.receipt', $paymentId) : route('resident.bill'),
                ]);
        });
    }

    /** Emails every active administrator. $status: submitted (needs verifying) or received (confirmed by PayMongo). */
    public function toAdmins(int $paymentId, string $status): void
    {
        app()->terminating(function () use ($paymentId, $status) {
            $details = $this->details($paymentId);
            if (!$details) {
                return;
            }
            $subject = $status === 'submitted'
                ? "AWAS: Payment to verify — {$details['consumer']} ({$details['reference']})"
                : "AWAS: Online payment received — {$details['consumer']} ({$details['reference']})";
            $admins = DB::table('users')->where('role', 'admin')->where('status', 'active')->get(['full_name', 'email']);
            foreach ($admins as $admin) {
                if (filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
                    $this->mailer->send($admin->email, $admin->full_name, $subject, 'emails.payment-status', $details + [
                        'name'   => $admin->full_name,
                        'status' => $status,
                        'admin'  => true,
                        'url'    => route('admin.payments.index', ['tab' => $status === 'submitted' ? 'pending' : 'paid']),
                    ]);
                }
            }
        });
    }

    /** What the email shows, read after the payment was saved. */
    private function details(int $paymentId): ?array
    {
        $row = DB::table('payments as p')
            ->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('consumers as c', 'c.consumer_id', '=', 'p.consumer_id')
            ->where('p.payment_id', $paymentId)
            ->first(['p.*', 'b.billing_period', 'b.total_amount', 'b.amount_paid as bill_paid', 'b.status as bill_status',
                'c.full_name as consumer', 'c.meter_number', 'c.user_id']);
        if (!$row) {
            return null;
        }
        return [
            'user_id'   => $row->user_id,
            'reference' => $row->payment_reference,
            'consumer'  => $row->consumer,
            'meter'     => $row->meter_number,
            'period'    => billingPeriodLabel($row->billing_period),
            'amount'    => formatCurrency($row->amount_paid),
            'method'    => paymentMethodLabel($row->payment_method, $row->wallet ?? null),
            'gatewayRef'=> $row->payment_method === 'gcash' ? $row->payment_gateway_txn_id : null,
            'date'      => date('F j, Y g:i A', strtotime($row->verified_at ?? $row->payment_date)),
            'balance'   => formatCurrency(max(0, (float)$row->total_amount - (float)$row->bill_paid)),
            'billPaid'  => $row->bill_status === 'paid',
            'reason'    => $row->rejection_reason,
        ];
    }
}
