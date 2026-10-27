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

    /* ---------------- Collection monitoring (admin) ---------------- */

    /** Daily / Weekly / Monthly: the chart's steps, and the "current" period the records table shows. */
    public const COLLECTION_RANGES = [
        'day'   => ['tab' => 'Daily',   'current' => 'Today',      'previous' => 'yesterday'],
        'week'  => ['tab' => 'Weekly',  'current' => 'This Week',  'previous' => 'last week'],
        'month' => ['tab' => 'Monthly', 'current' => 'This Month', 'previous' => 'last month'],
    ];

    /** Start and end of today / this week (Monday–Sunday) / this month, or $back of them earlier. */
    public static function collectionRange(string $range, int $back = 0): array
    {
        $now = now();
        return match ($range) {
            'day'   => [$now->copy()->subDays($back)->startOfDay(), $now->copy()->subDays($back)->endOfDay()],
            'week'  => [$now->copy()->subWeeks($back)->startOfWeek(), $now->copy()->subWeeks($back)->endOfWeek()],
            default => [$s = $now->copy()->startOfMonth()->subMonthsNoOverflow($back), $s->copy()->endOfMonth()],
        };
    }

    /** Paid (verified) payments received between two moments, by the date the money came in. */
    private function collectedBetween($from, $to)
    {
        return DB::table('payments')->where('status', 'verified')->whereBetween('payment_date', [$from, $to]);
    }

    /** Today, this week and this month: amount, number of payments, and the same for the period before. */
    public function collectionTotals(): array
    {
        $totals = [];
        foreach (array_keys(self::COLLECTION_RANGES) as $range) {
            [$now, $before] = array_map(function ($back) use ($range) {
                $q = $this->collectedBetween(...self::collectionRange($range, $back));
                return ['amount' => (float)(clone $q)->sum('amount_paid'), 'count' => (clone $q)->count()];
            }, [0, 1]);
            $totals[$range] = $now + ['previous' => $before['amount'], 'change' => self::change($now['amount'], $before['amount'])];
        }
        return $totals;
    }

    /**
     * Collections per step for the chart: the last 30 days, 12 weeks or 12 months,
     * each with its total, number of payments and amount per method.
     */
    public function collectionSeries(string $range): array
    {
        [$steps, $key, $label] = match ($range) {
            'day'   => [30, fn ($d) => $d->format('Y-m-d'), fn ($d) => $d->format('M j')],
            'week'  => [12, fn ($d) => $d->copy()->startOfWeek()->format('Y-m-d'), fn ($d) => $d->copy()->startOfWeek()->format('M j')],
            default => [12, fn ($d) => $d->format('Y-m'), fn ($d) => $d->format("M 'y")],
        };
        $buckets = [];
        for ($back = $steps - 1; $back >= 0; $back--) {
            $start = self::collectionRange($range, $back)[0];
            $buckets[$key($start)] = ['label' => $label($start), 'total' => 0.0, 'count' => 0, 'methods' => []];
        }

        $since = self::collectionRange($range, $steps - 1)[0];
        $rows = $this->collectedBetween($since, now()->endOfDay())->get(['payment_date', 'amount_paid', 'payment_method', 'wallet']);
        foreach ($rows as $r) {
            $k = $key(\Illuminate\Support\Carbon::parse($r->payment_date));
            if (!isset($buckets[$k])) {
                continue;
            }
            $method = paymentMethodLabel($r->payment_method, $r->wallet);
            $buckets[$k]['total'] += (float)$r->amount_paid;
            $buckets[$k]['count']++;
            $buckets[$k]['methods'][$method] = ($buckets[$k]['methods'][$method] ?? 0) + (float)$r->amount_paid;
        }
        return array_values($buckets);
    }

    /**
     * The payments collected today / this week / this month, newest first, with
     * the total and a subtotal per method. $limit = null returns every row (print, export).
     */
    public function collectionRecords(string $range, ?int $limit = null): array
    {
        [$from, $to] = self::collectionRange($range);
        $base = DB::table('payments as p')->where('p.status', 'verified')->whereBetween('p.payment_date', [$from, $to]);

        $byMethod = (clone $base)->groupBy('p.payment_method', 'p.wallet')
            ->selectRaw('p.payment_method, p.wallet, COUNT(*) AS cnt, SUM(p.amount_paid) AS amount')->get()
            ->groupBy(fn ($r) => paymentMethodLabel($r->payment_method, $r->wallet))
            ->map(fn ($rows) => ['count' => (int)$rows->sum('cnt'), 'amount' => (float)$rows->sum('amount')])
            ->sortByDesc('amount');

        $rows = (clone $base)
            ->join('consumers as c', 'c.consumer_id', '=', 'p.consumer_id')
            ->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'p.received_by')
            ->orderByDesc('p.payment_date')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get(['p.payment_id', 'p.payment_reference', 'p.amount_paid', 'p.payment_method', 'p.wallet', 'p.channel', 'p.payment_date',
                'p.payment_gateway_txn_id', 'c.full_name', 'c.meter_number', 'b.billing_period', 'u.full_name as received_by']);

        return [
            'rows'     => $rows,
            'count'    => (int)$byMethod->sum('count'),
            'total'    => (float)$byMethod->sum('amount'),
            'byMethod' => $byMethod,
            'from'     => $from,
            'to'       => $to,
        ];
    }

    /** "Oct 27, 2026", "Oct 26 – Nov 1, 2026" or "October 2026" for a range's current period. */
    public static function collectionRangeLabel(string $range, $from, $to): string
    {
        return match ($range) {
            'day'   => $from->format('l, M j, Y'),
            'week'  => $from->format('M j') . ' – ' . $to->format('M j, Y'),
            default => $from->format('F Y'),
        };
    }

    /** Percent change from $before to $now, or null when there is nothing to compare with. */
    public static function change(?float $now, ?float $before): ?float
    {
        return $now !== null && $before !== null && $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }
}
