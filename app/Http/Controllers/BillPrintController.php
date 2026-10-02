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

        $minCharge   = (float)setting('minimum_charge', 150);
        $includedCum = (float)setting('minimum_cubic_meters', 10);
        $balance = max(0, round((float)$row->total_amount - (float)$row->amount_paid, 2));
        $previousBalance = $billing->unpaidBalance((int)$row->consumer_id, (int)$row->bill_id);

        return view('bills.print', [
            'bill'            => $row,
            'barangayName'    => setting('barangay_name', 'Barangay Adlay'),
            'minCharge'       => $minCharge,
            'includedCum'     => $includedCum,
            'excessRate'      => (float)setting('excess_rate_per_cubic_meter', 15),
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
