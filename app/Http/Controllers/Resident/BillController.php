<?php

namespace App\Http\Controllers\Resident;

use App\Services\ApplicationFiles;
use App\Services\BillingService;
use App\Services\GcashQr;
use App\Services\GcashReceiptReader;
use App\Services\PayMongo;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Current (unpaid) bills and online payment. The only online method is the
 * barangay's GCash QR code: the consumer scans it, pays in GCash, then
 * clicks "I Have Paid" with a screenshot of the GCash receipt. The payment stays
 * "Pending Verification" until an administrator verifies it.
 */
class BillController extends ResidentController
{
    public function index(Request $request, BillingService $billing, PayMongo $paymongo)
    {
        $billing->refreshOverdueBills();

        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }

        // PayMongo payments not confirmed yet (e.g. the tab was closed after paying): ask PayMongo again.
        DB::table('payments')->where('consumer_id', $consumer->consumer_id)
            ->where('payment_method', 'paymongo')->where('status', 'pending')->get()
            ->each(fn ($p) => $paymongo->verifyAndCredit($p));

        $openBills = DB::table('water_bills as b')
            ->leftJoin('meter_readings as mr', 'mr.reading_id', '=', 'b.reading_id')
            ->where('b.consumer_id', $consumer->consumer_id)
            ->where('b.status', '!=', 'paid')->orderBy('b.due_date')
            ->get(['b.*', 'mr.previous_reading', 'mr.current_reading']);

        $billIds = $openBills->pluck('bill_id');
        return view('resident.bill', [
            'consumer'     => $consumer,
            'openBills'    => $openBills,
            'totalBalance' => $billing->unpaidBalance((int)$consumer->consumer_id),
            // Latest online payment per bill: pending → "Pending Verification", rejected → show the reason.
            'lastPayment'  => DB::table('payments')->whereIn('bill_id', $billIds)->where('channel', 'online')
                ->orderBy('payment_id')->get()->keyBy('bill_id'),
        ]);
    }

    /** Payment page for one bill: GCash via PayMongo (automatic), or the barangay GCash QR. */
    public function pay(Request $request, int $bill, PaymentService $payments, GcashQr $qr, PayMongo $paymongo)
    {
        $consumer = $this->consumer($request);
        $row = $consumer ? DB::table('water_bills as b')
            ->leftJoin('meter_readings as mr', 'mr.reading_id', '=', 'b.reading_id')
            ->where('b.bill_id', $bill)->where('b.consumer_id', $consumer->consumer_id)
            ->first(['b.*', 'mr.previous_reading', 'mr.current_reading']) : null;
        if (!$row) {
            flash('danger', 'Bill not found.');
            return redirect()->route('resident.bill');
        }
        if ($row->status === 'paid' || $payments->balance($row) <= 0) {
            flash('success', 'This bill is already paid.');
            return redirect()->route('resident.bill');
        }
        $pending = $payments->pendingPayment($row->bill_id);
        // An unfinished PayMongo checkout: credit it if it was paid, otherwise cancel it so the resident can start again.
        if ($pending && $pending->payment_method === 'paymongo' && $paymongo->settleOrCancel($pending)) {
            flash('success', 'This bill is already paid through PayMongo.');
            return redirect()->route('resident.bill');
        }
        if ($payments->pendingPayment($row->bill_id)) {
            flash('warning', 'You already submitted a payment for this bill. It is waiting for verification by the water office.');
            return redirect()->route('resident.bill');
        }

        $data = [
            'consumer' => $consumer,
            'bill'     => $row,
            'amount'   => $payments->balance($row),
            'qr'       => $qr,
            'paymongo' => $paymongo->isConfigured(),
            'wallets'  => $paymongo->enabledWallets(),
        ];
        // ?partial=1: only the panel, for the Pay Bill pop-up on Current Bills.
        return $request->boolean('partial')
            ? view('resident._pay-panel', $data + ['inModal' => true])
            : view('resident.pay', $data);
    }

    /** Resident picked an e-wallet (GCash, Maya, …) — sends them to PayMongo's checkout page. */
    public function payWithPayMongo(Request $request, int $bill, PayMongo $paymongo)
    {
        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }
        $wallet = clean($request->input('wallet'));
        if (!isset($paymongo->enabledWallets()[$wallet])) {
            flash('danger', 'That e-wallet is not available. Please pay with the QR code instead.');
            return redirect()->route('resident.bill');
        }
        [$checkoutUrl, $error] = $paymongo->startCheckout($consumer, $bill, $request->user()->user_id, $wallet);
        if ($error) {
            flash('danger', $error);
            return redirect()->route('resident.bill');
        }
        return redirect()->away($checkoutUrl);
    }

    /** "Show QR code": a QR Ph code for this bill's balance, made by PayMongo (JSON for the Pay Bill pop-up). */
    public function startQr(Request $request, int $bill, PayMongo $paymongo)
    {
        $consumer = $this->consumer($request);
        if (!$consumer) {
            return response()->json(['error' => 'Your login is not linked to a water account.'], 422);
        }
        [$qr, $error] = $paymongo->startOnlineQr((int)$consumer->consumer_id, $bill, $request->user()->user_id);
        return $error ? response()->json(['error' => $error], 422) : response()->json($qr);
    }

    /** Asked every few seconds while the QR is on screen: paid, still waiting, or expired. */
    public function qrStatus(Request $request, int $payment, PayMongo $paymongo)
    {
        $row = $this->ownQrPayment($request, $payment);
        if (!$row) {
            return response()->json(['status' => 'gone']);
        }
        if ($paymongo->verifyAndCredit($row)) {
            flash('success', 'Payment successful! Your ' . formatCurrency($row->amount_paid) . ' QR payment was confirmed — your bill is now Paid.');
            session()->flash('receipt_payment_id', $row->payment_id);
            return response()->json(['status' => 'paid', 'redirect' => route('resident.bill')]);
        }
        if ($paymongo->walletPaymentFailed($row)) {
            $paymongo->settleOrCancel($row);
            return response()->json(['status' => 'expired']);
        }
        return response()->json(['status' => 'waiting']);
    }

    /** "Cancel" while the QR is on screen — unless it was paid in the meantime. */
    public function cancelQr(Request $request, int $payment, PayMongo $paymongo)
    {
        $row = $this->ownQrPayment($request, $payment);
        $paid = $row && $paymongo->settleOrCancel($row);
        if ($paid) {
            flash('success', 'Your payment was already received — your bill is now Paid.');
            session()->flash('receipt_payment_id', $row->payment_id);
        }
        return response()->json(['paid' => $paid, 'redirect' => route('resident.bill')]);
    }

    /** One of this consumer's own online QR payments. */
    private function ownQrPayment(Request $request, int $paymentId): ?object
    {
        $consumer = $this->consumer($request);
        return $consumer ? DB::table('payments')->where('payment_id', $paymentId)->where('consumer_id', $consumer->consumer_id)
            ->where('payment_method', 'paymongo')->where('wallet', 'qrph')->where('channel', 'online')->first() : null;
    }

    /** PayMongo sends the resident back here after paying (or cancelling). */
    public function payMongoReturn(Request $request, int $bill, PayMongo $paymongo)
    {
        $consumer = $this->consumer($request);
        $payment = $consumer ? DB::table('payments')->where('payment_id', (int)$request->query('payment'))
            ->where('bill_id', $bill)->where('consumer_id', $consumer->consumer_id)->where('payment_method', 'paymongo')->first() : null;
        if (!$payment) {
            return redirect()->route('resident.bill');
        }

        // Back from the wallet: paid, or cancelled / failed there (then the attempt is cleared so they can pay again).
        $cancelled = $request->boolean('cancel') || $paymongo->walletPaymentFailed($payment);
        $paid = $cancelled ? $paymongo->settleOrCancel($payment) : $paymongo->verifyAndCredit($payment);
        if ($paid) {
            flash('success', 'Payment successful! Your ' . formatCurrency($payment->amount_paid) . ' ' . paymentMethodLabel('paymongo', $payment->wallet) . ' payment was confirmed — your bill is now Paid.');
            session()->flash('receipt_payment_id', $payment->payment_id);
        } elseif ($cancelled) {
            flash('warning', 'Payment was not completed. Your bill was not paid — you can try again.');
        } else {
            flash('warning', 'PayMongo has not confirmed your payment yet. If you completed it, your bill will change to Paid shortly.');
        }
        return redirect()->route('resident.bill');
    }

    /** "I Have Paid" — saves the payment as Pending Verification and notifies the administrators. */
    public function submitPayment(Request $request, int $bill, PaymentService $payments, ApplicationFiles $files, GcashReceiptReader $reader)
    {
        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }

        // The GCash receipt screenshot is the proof of payment; stored privately (only administrators can open it).
        $errors = [];
        $receipt = $files->in('payment-receipts')->store($request->file('receipt'), '', 'GCash receipt screenshot', true, $errors);
        if ($errors) {
            flash('danger', $errors[0]);
            return redirect()->route('resident.bill.pay', $bill)->withInput();
        }

        // The Ref. No. and amount are read from the screenshot, then checked.
        // When it can't be read automatically, it is saved anyway and the water office reads it when verifying.
        [$read, $error] = $reader->read($receipt);
        $payment = null;
        if (!$error) {
            [$payment, $error] = $payments->submitOnline($consumer, $bill, $read['ref'] ?? null, $read['amount'] ?? null, $receipt, $request->user()->user_id);
        }
        if ($error) {
            $files->in('payment-receipts')->delete($receipt);
            flash('danger', $error);
            return redirect()->route('resident.bill.pay', $bill)->withInput();
        }

        flash('success', 'Payment submitted! Your ' . formatCurrency($payment->amount_paid)
            . ' GCash payment is now Pending Verification. Your bill will change to Paid once the water office verifies it.');
        session()->flash('receipt_payment_id', $payment->payment_id);
        return redirect()->route('resident.bill');
    }
}
