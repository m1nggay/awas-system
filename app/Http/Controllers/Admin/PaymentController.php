<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ApplicationFiles;
use App\Services\GcashQr;
use App\Services\GcashReceiptReader;
use App\Services\PayMongo;
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

    public function index(Request $request, GcashQr $qr, PayMongo $paymongo)
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
            ->when(in_array($methodFil, ['cash', 'gcash', 'paymongo'], true), fn ($q) => $q->where('p.payment_method', $methodFil))
            ->when(in_array($channelFil, ['counter', 'online'], true), fn ($q) => $q->where('p.channel', $channelFil))
            ->orderByRaw($tab === 'pending' ? 'p.payment_date ASC' : 'p.payment_date DESC')
            ->select('p.*', 'c.full_name', 'c.meter_number', 'b.billing_period', 'b.total_amount as bill_total', 'u.full_name as staff_name')
            ->paginate(15)->appends($request->query());

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
            'paymongo'        => $paymongo->isConfigured(),
        ]);
    }

    /** Consumer paid in person at the barangay: Cash or GCash QR — recorded as Paid. */
    public function store(Request $request, PaymentService $payments, ApplicationFiles $files, GcashReceiptReader $reader)
    {
        $method = clean($request->input('payment_method'));
        $amount = (float)$request->input('amount_paid', 0);
        $gcashRef = '';

        // GCash at the barangay: the screenshot of the consumer's GCash payment is the proof,
        // and its Ref. No. and amount are read from it.
        $receipt = null;
        if ($method === 'gcash') {
            $errors = [];
            $receipt = $files->in('payment-receipts')->store($request->file('gcash_screenshot'), '', 'GCash payment screenshot', true, $errors);
            if ($errors) {
                return back()->withInput()->with('pay_error', $errors[0]);
            }
            [$read, $error] = $reader->read($receipt);
            if (!$read && !$error) {
                $error = 'The screenshot could not be checked automatically. Please try again, or record the payment as Cash after checking the GCash app.';
            }
            if ($error) {
                $files->in('payment-receipts')->delete($receipt);
                return back()->withInput()->with('pay_error', $error);
            }
            [$gcashRef, $amount] = [$read['ref'], $read['amount']];
        }

        [$payment, $error] = $payments->recordAtCounter(
            (int)$request->input('bill_id', 0),
            $method,
            $amount,
            $gcashRef,
            $receipt,
            clean($request->input('remarks')) ?: null,
            $request->user()->user_id
        );

        if ($error) {
            $files->in('payment-receipts')->delete($receipt);
            return back()->withInput()->with('pay_error', $error);
        }
        flash('success', paymentMethodLabel($method) . ' payment of ' . formatCurrency($payment->amount_paid)
            . " recorded — the bill is updated. Reference: {$payment->payment_reference}");

        // Back to the list without ?bill_id so the modal doesn't pop open again.
        return redirect()->route('admin.payments.index', ['tab' => 'paid']);
    }

    public function verify(Request $request, Payment $payment, PaymentService $payments)
    {
        try {
            // Ref. No. and amount are typed in when the screenshot could not be read automatically.
            $error = $payments->verify($payment->payment_id, $request->user()->user_id,
                $request->filled('gcash_reference') ? $payments->normalizeReference($request->input('gcash_reference')) : null,
                is_numeric($request->input('amount_seen')) ? (float)$request->input('amount_seen') : null);
        } catch (\Throwable $e) {
            Log::error('Payment verify failed: ' . $e->getMessage());
            $error = 'Failed to verify the payment. Please try again.';
        }
        $error ? flash('danger', $error) : flash('success', "Payment {$payment->payment_reference} verified — the bill is now updated.");
        return back();
    }

    /** At the barangay: a GCash / Maya QR code (QR Ph via PayMongo) for the chosen bill. */
    public function startQr(Request $request, PayMongo $paymongo)
    {
        [$qr, $error] = $paymongo->startCounterQr((int)$request->input('bill_id', 0), $request->user()->user_id);
        return $error ? response()->json(['error' => $error], 422) : response()->json($qr);
    }

    /** Asked every few seconds while the QR is on screen: paid, still waiting, or expired. */
    public function qrStatus(Payment $payment, PayMongo $paymongo)
    {
        $row = DB::table('payments')->where('payment_id', $payment->payment_id)->first();
        if (!$row || $row->payment_method !== 'paymongo') {
            return response()->json(['status' => 'gone']);
        }
        if ($paymongo->verifyAndCredit($row)) {
            session()->flash('success', 'GCash/Maya QR payment of ' . formatCurrency($row->amount_paid)
                . " received — confirmed by PayMongo, the bill is now Paid. Reference: {$row->payment_reference}");
            return response()->json(['status' => 'paid', 'redirect' => route('admin.payments.index', ['tab' => 'paid'])]);
        }
        if ($paymongo->walletPaymentFailed($row)) {
            $paymongo->settleOrCancel($row);
            return response()->json(['status' => 'expired']);
        }
        return response()->json(['status' => 'waiting']);
    }

    /** "Cancel" while the QR is on screen — unless it was paid in the meantime. */
    public function cancelQr(Payment $payment, PayMongo $paymongo)
    {
        $row = DB::table('payments')->where('payment_id', $payment->payment_id)->first();
        $paid = $row && $row->payment_method === 'paymongo' && $row->status === 'pending' && $paymongo->settleOrCancel($row);
        return response()->json(['paid' => $paid]);
    }

    /** An unfinished PayMongo payment: confirm it if PayMongo says it was paid, otherwise cancel it. */
    public function checkPayMongo(Payment $payment, PayMongo $paymongo)
    {
        $row = DB::table('payments')->where('payment_id', $payment->payment_id)->first();
        if ($row->payment_method !== 'paymongo' || $row->status !== 'pending') {
            flash('warning', 'This payment is no longer waiting for PayMongo.');
        } elseif ($paymongo->settleOrCancel($row)) {
            flash('success', "PayMongo confirmed payment {$row->payment_reference} — the bill is now Paid.");
        } elseif (DB::table('payments')->where('payment_id', $row->payment_id)->exists()) {
            flash('danger', 'PayMongo could not be reached. Please try again.');
        } else {
            flash('success', "Payment {$row->payment_reference} was not paid on PayMongo, so it was cancelled.");
        }
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
