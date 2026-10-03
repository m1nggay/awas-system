<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BillingService $billing)
    {
        $billing->refreshOverdueBills();
        $currentPeriod = currentBillingPeriod();

        if ($request->user()->isMeterReader()) {
            return $this->meterReaderDashboard($request, $currentPeriod);
        }

        $billsByStatus = ['unpaid' => 0, 'paid' => 0, 'overdue' => 0, 'partially_paid' => 0];
        $counts = DB::table('water_bills')->where('billing_period', $currentPeriod)
            ->groupBy('status')->selectRaw('status, COUNT(*) cnt')->pluck('cnt', 'status');
        foreach ($counts as $status => $cnt) {
            $billsByStatus[$status] = (int)$cnt;
        }

        $trend = DB::table('meter_readings')
            ->groupBy('billing_period')->orderByDesc('billing_period')->limit(6)
            ->selectRaw('billing_period, SUM(consumption) total_consumption')
            ->get()->reverse()->values();

        return view('admin.dashboard', [
            'currentPeriod'     => $currentPeriod,
            'totalConsumers'    => DB::table('consumers')->where('status', 'active')->count(),
            'totalConsumption'  => (float)DB::table('meter_readings')->where('billing_period', $currentPeriod)->sum('consumption'),
            'billsByStatus'     => $billsByStatus,
            'currentMonthBills' => array_sum($billsByStatus),
            'totalPayments'     => (float)DB::table('payments')->where('status', 'verified')->sum('amount_paid'),
            'recentPayments'    => DB::table('payments as p')
                ->join('consumers as c', 'c.consumer_id', '=', 'p.consumer_id')
                ->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
                ->orderByDesc('p.payment_date')->limit(8)
                ->get(['p.*', 'c.full_name', 'c.meter_number', 'b.billing_period']),
            'trend'             => $trend,
        ]);
    }

    /** The Meter Reader's simple home page: this period's progress and their latest readings. */
    private function meterReaderDashboard(Request $request, string $period)
    {
        $allowed = $request->user()->assignedPurokIds() ?: [0];   // only this reader's puroks
        $mine = DB::table('consumers')->where('status', 'active')->whereIn('purok_id', $allowed);
        $readIds = DB::table('meter_readings')->where('billing_period', $period)
            ->whereIn('consumer_id', (clone $mine)->select('consumer_id'))->pluck('consumer_id');

        return view('admin.reader-dashboard', [
            'period'         => $period,
            'assignedPuroks' => DB::table('puroks')->whereIn('purok_id', $allowed)->orderBy('purok_name')->pluck('purok_name'),
            'activeCount'    => (clone $mine)->count(),
            'readThisPeriod' => $readIds->count(),
            'myToday'        => DB::table('meter_readings')->where('recorded_by', $request->user()->user_id)
                ->where('created_at', '>=', now()->startOfDay())->count(),
            'toRead'         => DB::table('consumers as c')->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
                ->where('c.status', 'active')->whereIn('c.purok_id', $allowed)->whereNotIn('c.consumer_id', $readIds)
                ->orderBy('p.purok_name')->orderBy('c.full_name')->limit(15)
                ->get(['c.meter_number', 'c.full_name', 'p.purok_name']),
            'myReadings'     => DB::table('meter_readings as mr')
                ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
                ->leftJoin('water_bills as b', 'b.reading_id', '=', 'mr.reading_id')
                ->where('mr.recorded_by', $request->user()->user_id)
                ->orderByDesc('mr.created_at')->limit(8)
                ->get(['c.meter_number', 'c.full_name', 'mr.previous_reading', 'mr.current_reading', 'mr.consumption',
                    'b.total_amount', 'mr.reading_date']),
        ]);
    }
}
