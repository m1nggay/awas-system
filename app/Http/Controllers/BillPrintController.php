<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Printable water billing statement — administrators: any bill; consumers: only their own. */
class BillPrintController extends Controller
{
    public function __invoke(Request $request, int $bill, BillingService $billing)
    {
        $row = DB::table('water_bills as b')
            ->join('consumers as c', 'c.consumer_id', '=', 'b.consumer_id')
            ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->leftJoin('meter_readings as mr', 'mr.reading_id', '=', 'b.reading_id')
            ->where('b.bill_id', $bill)
            ->first(['b.*', 'c.full_name', 'c.meter_number', 'c.consumer_type', 'c.address', 'c.user_id', 'p.purok_name',
                'mr.previous_reading', 'mr.current_reading', 'mr.reading_date']);

        abort_if(!$row, 404, 'Bill not found.');

        $user = $request->user();
        if (!$user->isAdmin() && (int)$row->user_id !== (int)$user->user_id) {
            abort(403, 'You are not authorized to view this bill.');
        }

        $tariff      = $billing->tariff();
        $minCharge   = $tariff['minimumCharge'];
        $includedCum = $tariff['includedCum'];
        // One row per tier — unless the rates changed after this bill was made; then
        // a single "Excess Consumption" row with what was actually charged.
        $excessLines = $billing->excessLines((float)$row->consumption, $tariff);
        if (abs($minCharge + array_sum(array_column($excessLines, 'amount')) - (float)$row->amount_due) > 0.009) {
            $excessLines = null;
        }
        $balance = max(0, round((float)$row->total_amount - (float)$row->amount_paid, 2));
        // Left unpaid from earlier months (not paid in full) — carried onto this bill.
        $previousBalance = $billing->previousBalance((int)$row->consumer_id, $row->billing_period);

        return view('bills.print', [
            'backUrl'         => $user->isAdmin() ? route('admin.bills.index') : route('resident.bill'),
            'bill'            => $row,
            'barangayName'    => setting('barangay_name', 'Barangay Adlay'),
            'minCharge'       => $minCharge,
            'includedCum'     => $includedCum,
            'excessLines'     => $excessLines,
            'excessCum'       => max(0, (float)$row->consumption - $includedCum),
            'excessCharge'    => round((float)$row->amount_due - $minCharge, 2),
            'balance'         => $balance,
            'previousBalance' => $previousBalance,
            'datePaid'        => $row->status === 'paid'
                ? DB::table('payments')->where('bill_id', $row->bill_id)->where('status', 'verified')->max('payment_date')
                : null,
        ]);
    }
}
