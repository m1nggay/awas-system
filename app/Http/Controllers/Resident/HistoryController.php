<?php

namespace App\Http\Controllers\Resident;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoryController extends ResidentController
{
    public function bills(Request $request)
    {
        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }
        // Settled (fully paid) bills only — unpaid ones live on "Current Bills",
        // so the two pages no longer show the same bills.
        return view('resident.billing-history', [
            'bills' => DB::table('water_bills as b')
                ->leftJoin('meter_readings as mr', 'mr.reading_id', '=', 'b.reading_id')
                ->where('b.consumer_id', $consumer->consumer_id)->where('b.status', 'paid')
                ->orderByDesc('b.billing_period')
                ->get(['b.*', 'mr.previous_reading', 'mr.current_reading']),
            'datePaid' => DB::table('payments as p')->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
                ->where('b.consumer_id', $consumer->consumer_id)->where('p.status', 'verified')
                ->groupBy('p.bill_id')->selectRaw('p.bill_id, MAX(p.payment_date) AS paid_on')->pluck('paid_on', 'bill_id'),
        ]);
    }

    public function consumption(Request $request)
    {
        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }
        return view('resident.consumption', [
            'readings' => DB::table('meter_readings')->where('consumer_id', $consumer->consumer_id)->orderByDesc('billing_period')->get(),
        ]);
    }

    public function payments(Request $request)
    {
        $consumer = $this->consumer($request);
        if (!$consumer) {
            return redirect()->route('resident.dashboard');
        }
        return view('resident.payment-history', [
            'payments' => DB::table('payments as p')->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
                ->where('p.consumer_id', $consumer->consumer_id)->orderByDesc('p.payment_date')
                ->get(['p.*', 'b.billing_period']),
        ]);
    }
}
