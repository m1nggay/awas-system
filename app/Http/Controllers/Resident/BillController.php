<?php

namespace App\Http\Controllers\Resident;

use App\Services\ApplicationFiles;
use App\Services\BillingService;
use App\Services\GcashQr;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Current (unpaid) bills and online payment. The only online method is the
 * barangay's GCash QR code: the consumer scans it, pays in GCash, then
 * clicks "I Have Paid" with the GCash reference number. The payment stays
 * "Pending Verification" until an administrator verifies it.
 */
class BillController extends ResidentController
{
    public function index(Request $request, BillingService $billing)
    {
        $billing->refreshOverdueBills();

        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }

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

    /** GCash QR payment page for one bill. */
    public function pay(Request $request, int $bill, PaymentService $payments, GcashQr $qr)
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
        if ($payments->pendingPayment($row->bill_id)) {
            flash('warning', 'You already submitted a payment for this bill. It is waiting for verification by the water office.');
            return redirect()->route('resident.bill');
        }

        return view('resident.pay', [
            'consumer' => $consumer,
            'bill'     => $row,
            'amount'   => $payments->balance($row),
            'qr'       => $qr,
        ]);
    }

    /** "I Have Paid" — saves the payment as Pending Verification and notifies the administrators. */
    public function submitPayment(Request $request, int $bill, PaymentService $payments, ApplicationFiles $files)
    {
        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }

        // Optional receipt screenshot, stored privately (only administrators can open it).
        $receipt = null;
        if ($request->hasFile('receipt')) {
            $errors = [];
            $receipt = $files->in('payment-receipts')->store($request->file('receipt'), '', 'receipt screenshot', false, $errors);
            if ($errors) {
                flash('danger', $errors[0]);
                return back()->withInput();
            }
        }

        [$payment, $error] = $payments->submitOnline(
            $consumer, $bill, $payments->normalizeReference($request->input('gcash_reference')), $receipt, $request->user()->user_id
        );
        if ($error) {
            $files->in('payment-receipts')->delete($receipt);
            flash('danger', $error);
            return back()->withInput();
        }

        flash('success', 'Payment submitted! Your ' . formatCurrency($payment->amount_paid) . ' GCash payment (Ref. '
            . $payment->payment_gateway_txn_id . ') is now Pending Verification. Your bill will change to Paid once the water office verifies it.');
        session()->flash('receipt_payment_id', $payment->payment_id);
        return redirect()->route('resident.bill');
    }
}
