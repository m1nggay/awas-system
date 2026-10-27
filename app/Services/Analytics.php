<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard analytics: payment collection and water consumption by month,
 * with peaks and month-to-month changes.
 *
 *   admin()    — every consumer: billed vs collected, payment methods,
 *                consumption by purok, top consumers.
 *   consumer() — one household only: its own usage, bills and payments.
 *
 * Plain query-builder aggregates, so the same queries run on MySQL and PostgreSQL.
 */
class Analytics
{
    private const MONTHS = 12;

    public function admin(): array
    {
        $consumption = DB::table('meter_readings')->groupBy('billing_period')
            ->orderByDesc('billing_period')->limit(self::MONTHS)
            ->selectRaw('billing_period, SUM(consumption) AS total, COUNT(*) AS households')
            ->get()->reverse()->values()
            ->map(fn ($r) => (object)['period' => $r->billing_period, 'total' => (float)$r->total, 'households' => (int)$r->households]);

        $billing = DB::table('water_bills')->groupBy('billing_period')
            ->orderByDesc('billing_period')->limit(self::MONTHS)
            ->selectRaw('billing_period, SUM(total_amount) AS billed, SUM(amount_paid) AS collected')
            ->get()->reverse()->values()
            ->map(fn ($r) => (object)['period' => $r->billing_period, 'billed' => (float)$r->billed, 'collected' => (float)$r->collected,
                'rate' => (float)$r->billed > 0 ? round((float)$r->collected / (float)$r->billed * 100, 1) : null]);

        // Money received in a calendar month (by payment date), this month and last.
        $received = fn (\Carbon\CarbonInterface $month) => (float)DB::table('payments')->where('status', 'verified')
            ->whereBetween('payment_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('amount_paid');
        $thisMonth = now();
        $lastMonth = now()->subMonthNoOverflow();

        // How people pay (verified payments): by method, and at the barangay vs online.
        $methods = DB::table('payments')->where('status', 'verified')->groupBy('payment_method', 'wallet')
            ->selectRaw('payment_method, wallet, COUNT(*) AS cnt, SUM(amount_paid) AS amount')->get()
            ->groupBy(fn ($r) => paymentMethodLabel($r->payment_method, $r->wallet))
            ->map(fn ($rows, $label) => (object)['label' => $label, 'count' => (int)$rows->sum('cnt'), 'amount' => (float)$rows->sum('amount')])
            ->sortByDesc('count')->values();
        $channels = DB::table('payments')->where('status', 'verified')->groupBy('channel')
            ->selectRaw('channel, COUNT(*) AS cnt')->pluck('cnt', 'channel')->map(fn ($n) => (int)$n);

        $latest = $consumption->last();
        $previous = $consumption->count() > 1 ? $consumption[$consumption->count() - 2] : null;
        $avg = fn ($row) => $row && $row->households > 0 ? $row->total / $row->households : null;
        $latestBilling = $billing->last();
        $previousBilling = $billing->count() > 1 ? $billing[$billing->count() - 2] : null;

        return [
            'consumption'  => $consumption,
            'billing'      => $billing,
            'peak'         => $consumption->sortByDesc('total')->first(),
            'latestPeriod' => $latest?->period,
            'avgPerHousehold'     => $avg($latest),
            'avgPerHouseholdPrev' => $avg($previous),
            'previousPeriod'      => $previous?->period,
            'collectedThisMonth'  => $received($thisMonth),
            'collectedLastMonth'  => $received($lastMonth),
            'thisMonthLabel'      => $thisMonth->format('F Y'),
            'lastMonthLabel'      => $lastMonth->format('F'),
            'rate'          => $latestBilling?->rate,
            'ratePrev'      => $previousBilling?->rate,
            'ratePeriod'    => $latestBilling?->period,
            'ratePrevPeriod'=> $previousBilling?->period,
            'methods'       => $methods,
            'channels'      => $channels,
            'byPurok'       => $latest ? $this->byPurok($latest->period) : collect(),
            'topConsumers'  => $latest ? $this->topConsumers($latest->period) : collect(),
        ];
    }

    private function byPurok(string $period): Collection
    {
        return DB::table('meter_readings as mr')
            ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
            ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->where('mr.billing_period', $period)
            ->groupBy('p.purok_name')
            ->selectRaw('p.purok_name, SUM(mr.consumption) AS total, COUNT(*) AS households')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => (object)['purok' => $r->purok_name, 'total' => (float)$r->total, 'households' => (int)$r->households]);
    }

    /** The five highest users this period, each with their usual (average of up to 6 earlier months). */
    private function topConsumers(string $period): Collection
    {
        $top = DB::table('meter_readings as mr')
            ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
            ->where('mr.billing_period', $period)
            ->orderByDesc('mr.consumption')->limit(5)
            ->get(['c.consumer_id', 'c.full_name', 'c.meter_number', 'mr.consumption']);

        $history = DB::table('meter_readings')->whereIn('consumer_id', $top->pluck('consumer_id'))
            ->where('billing_period', '<', $period)->orderByDesc('billing_period')
            ->get(['consumer_id', 'consumption'])->groupBy('consumer_id');

        return $top->map(function ($r) use ($history) {
            $earlier = ($history[$r->consumer_id] ?? collect())->take(6);
            $usual = $earlier->isNotEmpty() ? (float)$earlier->avg('consumption') : null;
            return (object)[
                'name'   => $r->full_name,
                'meter'  => $r->meter_number,
                'usage'  => (float)$r->consumption,
                'usual'  => $usual,
                'change' => self::change((float)$r->consumption, $usual),
            ];
        });
    }

    public function consumer(int $consumerId): array
    {
        $usage = DB::table('meter_readings')->where('consumer_id', $consumerId)
            ->orderByDesc('billing_period')->limit(self::MONTHS)
            ->get(['billing_period', 'consumption'])->reverse()->values()
            ->map(fn ($r) => (object)['period' => $r->billing_period, 'usage' => (float)$r->consumption]);

        $bills = DB::table('water_bills')->where('consumer_id', $consumerId)
            ->orderByDesc('billing_period')->limit(self::MONTHS)
            ->get(['bill_id', 'billing_period', 'total_amount', 'amount_paid', 'status', 'due_date']);

        $payments = DB::table('payments')->whereIn('bill_id', $bills->pluck('bill_id'))
            ->whereIn('status', ['verified', 'pending'])->orderByDesc('payment_date')
            ->get(['bill_id', 'status', 'payment_date', 'payment_method', 'wallet'])->groupBy('bill_id');

        // One row per bill: what was billed and paid, when, how, and whether it was on time.
        $record = $bills->map(function ($b) use ($payments) {
            $rows = $payments[$b->bill_id] ?? collect();
            $paid = $rows->firstWhere('status', 'verified');
            $fullyPaid = $b->status === 'paid';
            $timing = match (true) {
                $fullyPaid && $paid => substr((string)$paid->payment_date, 0, 10) <= (string)$b->due_date ? 'on_time' : 'late',
                $fullyPaid => 'on_time',
                $rows->contains('status', 'pending') => 'pending',
                (float)$b->amount_paid > 0 => 'partial',
                default => $b->status === 'overdue' ? 'overdue' : 'unpaid',
            };
            return (object)[
                'period' => $b->billing_period,
                'billed' => (float)$b->total_amount,
                'paid'   => (float)$b->amount_paid,
                'date'   => $paid?->payment_date,
                'method' => $paid ? paymentMethodLabel($paid->payment_method, $paid->wallet) : null,
                'timing' => $timing,
            ];
        });

        $latest = $usage->last();
        $previous = $usage->count() > 1 ? $usage[$usage->count() - 2] : null;

        return [
            'usage'    => $usage,
            'latest'   => $latest,
            'previous' => $previous,
            'change'   => $latest && $previous ? self::change($latest->usage, $previous->usage) : null,
            'peak'     => $usage->sortByDesc('usage')->first(),
            'average'  => $usage->isNotEmpty() ? $usage->avg('usage') : null,
            'bills'    => $record->reverse()->values(),
            'record'   => $record,
            'paidThisYear' => (float)DB::table('payments')->where('consumer_id', $consumerId)->where('status', 'verified')
                ->where('payment_date', '>=', now()->startOfYear())->sum('amount_paid'),
        ];
    }

    /** Percent change from $before to $now, or null when there is nothing to compare with. */
    public static function change(?float $now, ?float $before): ?float
    {
        return $now !== null && $before !== null && $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }
}
