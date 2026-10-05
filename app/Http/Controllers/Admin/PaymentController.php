<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ApplicationFiles;
use App\Services\GcashQr;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment management: online GCash payments waiting for verification,
 * payments made in person at the barangay (Cash or GCash QR), and the
 * complete payment history.
 */
class PaymentController extends Controller
{
    private const TABS = [
        'pending'  => ['pending'],
        'paid'     => ['verified'],
        'rejected' => ['rejected', 'failed'],
        'all'      => null,
    ];

    public function index(Request $request, GcashQr $qr)
    {
        $search = clean($request->query('q'));
        $methodFil = clean($request->query('method'));
        $channelFil = clean($request->query('channel'));

        $counts = DB::table('payments')->groupBy('status')->selectRaw('status, COUNT(*) cnt')->pluck('cnt', 'status');
        $tabCounts = [
            'pending'  => (int)($counts['pending'] ?? 0),
            'paid'     => (int)($counts['verified'] ?? 0),
            'rejected' => (int)($counts['rejected'] ?? 0) + (int)($counts['failed'] ?? 0),
            'all'      => (int)$counts->sum(),
        ];
        $tab = clean($request->query('tab'));
        if (!array_key_exists($tab, self::TABS)) {
            $tab = $tabCounts['pending'] > 0 ? 'pending' : 'all';
        }

        $payments = DB::table('payments as p')
            ->join('consumers as c', 'c.consumer_id', '=', 'p.consumer_id')
            ->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'p.received_by')
            ->when(self::TABS[$tab] !== null, fn ($q) => $q->whereIn('p.status', self::TABS[$tab]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('c.full_name', like_operator(), "%$search%")
                ->orWhere('c.meter_number', like_operator(), "%$search%")
                ->orWhere('p.payment_reference', like_operator(), "%$search%")
                ->orWhere('p.payment_gateway_txn_id', like_operator(), "%$search%")))
            ->when(in_array($methodFil, ['cash', 'gcash'], true), fn ($q) => $q->where('p.payment_method', $methodFil))
            ->when(in_array($channelFil, ['counter', 'online'], true), fn ($q) => $q->where('p.channel', $channelFil))
            ->orderByRaw($tab === 'pending' ? 'p.payment_date ASC' : 'p.payment_date DESC')
            ->select('p.*', 'c.full_name', 'c.meter_number', 'b.billing_period', 'b.total_amount as bill_total', 'u.full_name as staff_name')
            ->paginate(15)->withQueryString();

        // Bills that can be paid at the counter (no online payment waiting).
        $payableBills = DB::table('water_bills as b')
            ->join('consumers as c', 'c.consumer_id', '=', 'b.consumer_id')
            ->whereIn('b.status', ['unpaid', 'partially_paid', 'overdue'])
            ->whereNotExists(fn ($q) => $q->from('payments as p')->whereColumn('p.bill_id', 'b.bill_id')->where('p.status', 'pending'))
            ->orderBy('c.meter_number')->orderBy('b.due_date')
            ->get(['b.bill_id', 'b.billing_period', 'b.total_amount', 'b.amount_paid', 'c.full_name', 'c.meter_number']);

        return view('admin.payments.index', [
            'payments'        => $payments,
            'payableBills'    => $payableBills,
            'preselectBillId' => (int)$request->query('bill_id', 0),
            'tab'             => $tab,
            'tabCounts'       => $tabCounts,
            'search'          => $search,
            'methodFil'       => $methodFil,
            'channelFil'      => $channelFil,
            'qr'              => $qr,
        ]);
    }

    /** Consumer paid in person at the barangay: Cash or GCash QR — recorded as Paid. */
    public function store(Request $request, PaymentService $payments, ApplicationFiles $files)
    {
        $method = clean($request->input('payment_method'));

        // GCash at the barangay: the screenshot of the consumer's GCash payment is the proof.
        $receipt = null;
        if ($method === 'gcash') {
            $errors = [];
            $receipt = $files->in('payment-receipts')->store($request->file('gcash_screenshot'), '', 'GCash payment screenshot', true, $errors);
            if ($errors) {
                flash('danger', $errors[0]);
                return back()->withInput();
            }
        }

        [$payment, $error] = $payments->recordAtCounter(
            (int)$request->input('bill_id', 0),
            $method,
            (float)$request->input('amount_paid', 0),
            $receipt,
            clean($request->input('remarks')) ?: null,
            $request->user()->user_id
        );

        if ($error) {
            $files->in('payment-receipts')->delete($receipt);
            flash('danger', $error);
            return back()->withInput();
        }
        flash('success', paymentMethodLabel($method) . ' payment of ' . formatCurrency($payment->amount_paid)
            . " recorded — the bill is updated. Reference: {$payment->payment_reference}");

        // Back to the list without ?bill_id so the modal doesn't pop open again.
        return redirect()->route('admin.payments.index', ['tab' => 'paid']);
    }

    public function verify(Request $request, Payment $payment, PaymentService $payments)
    {
        try {
            $error = $payments->verify($payment->payment_id, $request->user()->user_id);
        } catch (\Throwable $e) {
            Log::error('Payment verify failed: ' . $e->getMessage());
            $error = 'Failed to verify the payment. Please try again.';
        }
        $error ? flash('danger', $error) : flash('success', "Payment {$payment->payment_reference} verified — the bill is now updated.");
        return back();
    }

    public function reject(Request $request, Payment $payment, PaymentService $payments)
    {
        $reason = clean($request->input('rejection_reason'));
        if ($reason === '') {
            flash('danger', 'Please give the reason for rejecting this payment (the consumer will see it).');
            return back();
        }
        $error = $payments->reject($payment->payment_id, $request->user()->user_id, $reason);
        $error ? flash('danger', $error) : flash('success', "Payment {$payment->payment_reference} rejected. The consumer was notified and can pay again.");
        return back();
    }

    /** The receipt screenshot a consumer attached (private file, admins only). */
    public function receipt(Payment $payment, ApplicationFiles $files)
    {
        return $files->in('payment-receipts')->response($payment->receipt_file, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ], 'No receipt attached.');
    }
}
