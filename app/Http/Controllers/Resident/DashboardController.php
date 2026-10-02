<?php

namespace App\Http\Controllers\Resident;

use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ResidentController
{
    public function __invoke(Request $request, BillingService $billing)
    {
        $billing->refreshOverdueBills();

        $consumer = $this->consumer($request);
        if (!$consumer) {
            return view('resident.not-linked');
        }
        $cid = $consumer->consumer_id;

        return view('resident.dashboard', [
            'consumer'       => $consumer,
            'totalBalance'   => $billing->unpaidBalance((int)$cid),
            'currentBill'    => $currentBill = DB::table('water_bills')->where('consumer_id', $cid)->where('status', '!=', 'paid')->orderBy('due_date')->first(),
            'currentPending' => $currentBill && DB::table('payments')->where('bill_id', $currentBill->bill_id)->where('status', 'pending')->exists(),
            'latestReading'  => DB::table('meter_readings')->where('consumer_id', $cid)->orderByDesc('billing_period')->first(),
            'trend'          => DB::table('meter_readings')->where('consumer_id', $cid)->orderByDesc('billing_period')->limit(6)
                ->get(['billing_period', 'consumption'])->reverse()->values(),
            'recentPayments' => DB::table('payments as p')->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
                ->where('p.consumer_id', $cid)->orderByDesc('p.payment_date')->limit(5)
                ->get(['p.*', 'b.billing_period']),
        ]);
    }
}
