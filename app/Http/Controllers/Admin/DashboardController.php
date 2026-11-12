<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BillingService $billing, Analytics $analytics)
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
            'analytics'         => $analytics->admin(),
            // Collection Monitoring: the Daily / Weekly / Monthly switch (?collect=).
            'collect'           => $collect = array_key_exists($c = clean($request->query('collect')), Analytics::COLLECTION_RANGES) ? $c : 'day',
            'collectTotals'     => $analytics->collectionTotals(),
            'collectSeries'     => $analytics->collectionSeries($collect),
            'collectRecords'    => $analytics->collectionRecords($collect, 10),
        ]);
    }

    /**
     * The Meter Reader's home page, made for field work: this month's reading round (overall and per
     * purok), what is left to read, their pace over the last 14 days, and their readings this month
     * that deserve a second look (the photo didn't confirm it, 0 m³ used, or far more than usual).
     */
    private function meterReaderDashboard(Request $request, string $period)
    {
        $userId = $request->user()->user_id;
        $allowed = $request->user()->assignedPurokIds() ?: [0];   // only this reader's puroks
        $mine = DB::table('consumers')->where('status', 'active')->whereIn('purok_id', $allowed);
        $readIds = DB::table('meter_readings')->where('billing_period', $period)
            ->whereIn('consumer_id', (clone $mine)->select('consumer_id'))->pluck('consumer_id');

        // Progress of each assigned purok this month.
        $active = (clone $mine)->groupBy('purok_id')->selectRaw('purok_id, COUNT(*) AS n')->pluck('n', 'purok_id');
        $read = DB::table('consumers as c')->join('meter_readings as mr', 'mr.consumer_id', '=', 'c.consumer_id')
            ->where('mr.billing_period', $period)->where('c.status', 'active')->whereIn('c.purok_id', $allowed)
            ->groupBy('c.purok_id')->selectRaw('c.purok_id, COUNT(DISTINCT c.consumer_id) AS n')->pluck('n', 'c.purok_id');
        $purokProgress = DB::table('puroks')->whereIn('purok_id', $allowed)->orderBy('purok_name')->get(['purok_id', 'purok_name'])
            ->map(fn ($p) => (object)['name' => $p->purok_name, 'read' => (int)($read[$p->purok_id] ?? 0), 'total' => (int)($active[$p->purok_id] ?? 0)]);

        // My readings per day, last 14 days (today last).
        $from = now()->subDays(13)->startOfDay();
        $perDay = DB::table('meter_readings')->where('recorded_by', $userId)->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS n')->groupByRaw('DATE(created_at)')->pluck('n', 'd');
        $daily = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $from->copy()->addDays($i);
            $daily[] = ['label' => localizeMonths($day->format('M j')), 'count' => (int)($perDay[$day->toDateString()] ?? 0)];
        }

        return view('admin.reader-dashboard', [
            'period'         => $period,
            'assignedPuroks' => DB::table('puroks')->whereIn('purok_id', $allowed)->orderBy('purok_name')->pluck('purok_name'),
            'activeCount'    => (clone $mine)->count(),
            'readThisPeriod' => $readIds->count(),
            'purokProgress'  => $purokProgress,
            'myToday'        => DB::table('meter_readings')->where('recorded_by', $userId)
                ->where('created_at', '>=', now()->startOfDay())->count(),
            'myWeek'         => DB::table('meter_readings')->where('recorded_by', $userId)
                ->where('created_at', '>=', now()->startOfWeek())->count(),
            'daily'          => $daily,
            'toCheck'        => $this->readingsToCheck($userId, $period, $allowed),
            // The whole list (searchable and filtered by purok on the page).
            'toRead'         => DB::table('consumers as c')->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
                ->where('c.status', 'active')->whereIn('c.purok_id', $allowed)->whereNotIn('c.consumer_id', $readIds)
                ->orderBy('p.purok_name')->orderBy('c.full_name')
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

    /**
     * This month's readings by this meter reader that deserve a second look, with the reason:
     * the photo didn't confirm a typed reading, no water used at all, or far more than usual
     * (more than twice the household's average of up to 6 earlier months, and at least 10 m³ more).
     */
    private function readingsToCheck(int $userId, string $period, array $allowed)
    {
        $rows = DB::table('meter_readings as mr')->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
            ->where('mr.recorded_by', $userId)->where('mr.billing_period', $period)->whereIn('c.purok_id', $allowed)
            ->orderByDesc('mr.created_at')
            ->get(['mr.consumer_id', 'c.meter_number', 'c.full_name', 'mr.consumption', 'mr.photo_check']);
        if ($rows->isEmpty()) {
            return collect();
        }

        $usual = [];
        DB::table('meter_readings')->whereIn('consumer_id', $rows->pluck('consumer_id'))->where('billing_period', '<', $period)
            ->orderByDesc('billing_period')->get(['consumer_id', 'consumption'])
            ->groupBy('consumer_id')->each(function ($earlier, $id) use (&$usual) {
                $usual[$id] = (float)$earlier->take(6)->avg('consumption');
            });

        return $rows->map(function ($r) use ($usual) {
            $used = (float)$r->consumption;
            $avg = $usual[$r->consumer_id] ?? 0.0;
            $r->reason = match (true) {
                in_array($r->photo_check, \App\Http\Controllers\Admin\MeterReadingController::NEEDS_REVIEW, true) => __('The photo didn\'t confirm the typed reading'),
                $used == 0.0 => __('0 m³ used — same as last month'),
                $avg > 0 && $used > max($avg * 2, $avg + 10) => __('Much more than usual (about :usual m³ a month)', ['usual' => number_format($avg, 1)]),
                default => null,
            };
            return $r;
        })->filter(fn ($r) => $r->reason !== null)->values();
    }
}
