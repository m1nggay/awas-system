<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeterReading;
use App\Models\Purok;
use App\Services\BillingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MeterReadingController extends Controller
{
    private const BILL_STATUSES = ['unpaid', 'partially_paid', 'paid', 'overdue'];

    public function index(Request $request, BillingService $billing)
    {
        $billing->refreshOverdueBills();

        $search = clean($request->query('q'));
        $purokFil = (int)$request->query('purok', 0);
        $statusFil = clean($request->query('status'));
        $periodFil = clean($request->query('period'));
        $allowed = $request->user()->assignedPurokIds();

        $readings = DB::table('meter_readings as mr')
            ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
            ->leftJoin('water_bills as b', 'b.reading_id', '=', 'mr.reading_id')
            ->when($allowed !== null, fn ($q) => $q->whereIn('c.purok_id', $allowed ?: [0]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('c.full_name', like_operator(), "%$search%")
                ->orWhere('c.meter_number', like_operator(), "%$search%")))
            ->when($purokFil > 0, fn ($q) => $q->where('c.purok_id', $purokFil))
            ->when(in_array($statusFil, self::BILL_STATUSES, true), fn ($q) => $q->where('b.status', $statusFil))
            ->when($periodFil !== '', fn ($q) => $q->where('mr.billing_period', $periodFil))
            ->orderByDesc('mr.reading_date')->orderByDesc('mr.reading_id')
            ->select('mr.*', 'c.full_name', 'c.meter_number', 'b.total_amount as bill_total',
                'b.amount_paid as bill_paid', 'b.status as bill_status')
            ->paginate(15)->appends($request->query());

        return view('admin.readings.index', [
            'readings'  => $readings,
            'puroks'    => Purok::when($allowed !== null, fn ($q) => $q->whereIn('purok_id', $allowed ?: [0]))->orderBy('purok_name')->get(),
            'noPuroks'  => $allowed === [],
            'periods'   => DB::table('meter_readings')->distinct()->orderByDesc('billing_period')->pluck('billing_period'),
            'tariff'    => $billing->tariff(),
            'search'    => $search,
            'purokFil'  => $purokFil,
            'statusFil' => $statusFil,
            'periodFil' => $periodFil,
        ]);
    }

    /** Recorded Readings: the readings this Meter Reader recorded, with a summary per billing period. */
    public function history(Request $request)
    {
        $search = clean($request->query('q'));
        $periodFil = clean($request->query('period'));
        $mine = DB::table('meter_readings as mr')->where('mr.recorded_by', $request->user()->user_id);

        $readings = (clone $mine)
            ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->leftJoin('water_bills as b', 'b.reading_id', '=', 'mr.reading_id')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('c.full_name', like_operator(), "%$search%")
                ->orWhere('c.meter_number', like_operator(), "%$search%")))
            ->when($periodFil !== '', fn ($q) => $q->where('mr.billing_period', $periodFil))
            ->orderByDesc('mr.reading_date')->orderByDesc('mr.reading_id')
            ->select('mr.*', 'c.full_name', 'c.meter_number', 'p.purok_name', 'b.total_amount as bill_total', 'b.status as bill_status')
            ->paginate(15)->appends($request->query());

        return view('admin.readings.history', [
            'readings'  => $readings,
            // Meters read and water used per billing period (latest 6).
            'summary'   => (clone $mine)->groupBy('mr.billing_period')->orderByDesc('mr.billing_period')->limit(6)
                ->selectRaw('mr.billing_period, COUNT(*) as total, SUM(mr.consumption) as consumption')->get(),
            'totalAll'  => (clone $mine)->count(),
            'today'     => (clone $mine)->where('mr.created_at', '>=', now()->startOfDay())->count(),
            'periods'   => (clone $mine)->distinct()->orderByDesc('mr.billing_period')->pluck('mr.billing_period'),
            'search'    => $search,
            'periodFil' => $periodFil,
        ]);
    }

    /** Suggestions while typing in Record Reading: by name or meter number, only in the reader's puroks. */
    public function search(Request $request)
    {
        $q = clean($request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $allowed = $request->user()->assignedPurokIds();

        $consumers = DB::table('consumers as c')->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->when($allowed !== null, fn ($w) => $w->whereIn('c.purok_id', $allowed ?: [0]))
            ->whereNotNull('c.meter_number')
            ->where(fn ($w) => $w->where('c.full_name', like_operator(), "%$q%")->orWhere('c.meter_number', like_operator(), "$q%"))
            ->orderBy('c.full_name')->limit(8)
            ->get(['c.meter_number', 'c.full_name', 'c.status', 'p.purok_name']);

        return response()->json($consumers->map(fn ($c) => [
            'meter' => $c->meter_number, 'name' => $c->full_name, 'purok' => $c->purok_name, 'active' => $c->status === 'active',
        ]));
    }

    public function lookup(Request $request, BillingService $billing)
    {
        $meter = clean($request->query('meter'));
        if (!isValidMeterNumber($meter)) {
            return response()->json(['found' => false, 'message' => 'Meter Number must contain numbers only.']);
        }

        $c = DB::table('consumers as c')->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->where('c.meter_number', $meter)
            ->first(['c.consumer_id', 'c.full_name', 'c.status', 'c.is_senior', 'c.consumer_type', 'c.initial_meter_reading', 'c.purok_id', 'p.purok_name']);
        if (!$c) {
            return response()->json(['found' => false, 'message' => "No consumer has Meter Number $meter."]);
        }
        if (!$request->user()->canAccessPurok((int)$c->purok_id)) {
            return response()->json(['found' => false, 'message' => "Meter Number $meter is in {$c->purok_name}, which is not assigned to you."]);
        }

        $last = DB::table('meter_readings')->where('consumer_id', $c->consumer_id)
            ->orderByDesc('billing_period')->first(['current_reading', 'billing_period']);
        $period = clean($request->query('period'));
        // Usual use (average of the last 3 months), to warn about an unusual new reading.
        $usual = (float)DB::table('meter_readings')->where('consumer_id', $c->consumer_id)
            ->orderByDesc('billing_period')->limit(3)->pluck('consumption')->avg();

        return response()->json([
            'found'            => true,
            'active'           => $c->status === 'active',
            'name'             => $c->full_name,
            'purok'            => $c->purok_name,
            'consumer_type'    => consumerTypeLabel($c->consumer_type),
            'is_senior'        => (bool)$c->is_senior,
            'previous_reading' => (float)($last->current_reading ?? $c->initial_meter_reading ?? 0),
            'last_period'      => $last ? billingPeriodLabel($last->billing_period) : null,
            'usual_consumption' => round($usual, 2),
            'balance'          => $billing->unpaidBalance($c->consumer_id),
            'already_read'     => $period !== '' && DB::table('meter_readings')
                ->where('consumer_id', $c->consumer_id)->where('billing_period', $period)->exists(),
        ]);
    }

    /** The form without the phone outbox (e.g. browser storage blocked): save now, then reload the page. */
    public function store(Request $request, BillingService $billing)
    {
        $result = $this->saveReading($request, $billing);
        flash($result['ok'] ? 'success' : 'danger', $result['message']);
        if (!$result['ok'] && !$result['duplicate']) {
            return back();
        }
        // Back to the Consumer List when it was opened from there; otherwise the readings list
        // (not back(), which would reopen the form for the meter just read).
        $to = (string)$request->input('back');
        return str_starts_with($to, url('/') . '/') ? redirect()->to($to) : redirect()->route('admin.readings.index');
    }

    /**
     * One reading sent from the phone's outbox (Pending list). Safe to repeat: a reading whose
     * client_ref is already saved is reported as saved again, so a resend after a dropped
     * connection never makes a second reading or bill.
     */
    public function sync(Request $request, BillingService $billing)
    {
        $ref = (string)$request->input('client_ref');
        if (!preg_match('/^[A-Za-z0-9-]{8,40}$/', $ref)) {
            return response()->json(['status' => 'error', 'message' => 'This reading has no valid reference. Please record it again.'], 422);
        }
        if ($saved = $this->savedByRef($ref)) {
            return response()->json($saved);
        }
        $result = $this->saveReading($request, $billing, $ref);
        if (!$result['ok'] && ($saved = $this->savedByRef($ref))) {
            return response()->json($saved);   // the same reading arrived twice at once; the other one won
        }
        return response()->json([
            'status'  => $result['ok'] ? 'saved' : 'error',
            'message' => $result['message'],
            'total'   => $result['total'] ?? null,
        ]);
    }

    /**
     * Everything the Record Reading form needs for the meter reader's puroks, so meters can be
     * looked up on the phone with slow or no internet (Download my puroks).
     */
    public function offlineData(Request $request, BillingService $billing)
    {
        $billing->refreshOverdueBills();
        $allowed = $request->user()->assignedPurokIds() ?: [0];

        $consumers = DB::table('consumers as c')->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->whereIn('c.purok_id', $allowed)->whereNotNull('c.meter_number')
            ->orderBy('c.full_name')
            ->get(['c.consumer_id', 'c.meter_number', 'c.full_name', 'c.status', 'c.is_senior', 'c.consumer_type',
                'c.initial_meter_reading', 'p.purok_name']);
        $ids = $consumers->pluck('consumer_id');

        $readings = DB::table('meter_readings')->whereIn('consumer_id', $ids)
            ->orderByDesc('billing_period')
            ->get(['consumer_id', 'billing_period', 'current_reading', 'consumption'])->groupBy('consumer_id');
        $balances = DB::table('water_bills')->whereIn('consumer_id', $ids)->where('status', '!=', 'paid')
            ->groupBy('consumer_id')->selectRaw('consumer_id, SUM(total_amount - amount_paid) as balance')
            ->pluck('balance', 'consumer_id');

        return response()->json([
            'downloaded_at' => now()->toIso8601String(),
            'consumers'     => $consumers->map(function ($c) use ($readings, $balances) {
                $mine = $readings->get($c->consumer_id, collect());
                $last = $mine->first();
                return [
                    'meter'             => $c->meter_number,
                    'name'              => $c->full_name,
                    'purok'             => $c->purok_name,
                    'consumer_type'     => consumerTypeLabel($c->consumer_type),
                    'is_senior'         => (bool)$c->is_senior,
                    'active'            => $c->status === 'active',
                    'previous_reading'  => (float)($last->current_reading ?? $c->initial_meter_reading ?? 0),
                    'last_period'       => $last ? billingPeriodLabel($last->billing_period) : null,
                    'read_periods'      => $mine->pluck('billing_period')->values(),
                    'usual_consumption' => round((float)$mine->take(3)->pluck('consumption')->avg(), 2),
                    'balance'           => round((float)($balances[$c->consumer_id] ?? 0), 2),
                ];
            })->values(),
        ]);
    }

    /** The answer for a reading the server already has (by the phone's client_ref), or null. */
    private function savedByRef(string $ref): ?array
    {
        $row = DB::table('meter_readings as mr')->leftJoin('water_bills as b', 'b.reading_id', '=', 'mr.reading_id')
            ->where('mr.client_ref', $ref)->first(['mr.reading_id', 'b.total_amount']);
        return $row ? ['status' => 'saved', 'message' => 'Saved.', 'total' => (float)$row->total_amount] : null;
    }

    /**
     * Validates and saves one reading and generates its bill.
     * Returns ['ok' => bool, 'duplicate' => bool, 'message' => string, 'total' => ?float].
     */
    private function saveReading(Request $request, BillingService $billing, ?string $clientRef = null): array
    {
        $meter = clean($request->input('meter_number'));
        $period = clean($request->input('billing_period'));
        $previousRaw = trim((string)$request->input('previous_reading', ''));
        $currentRaw = trim((string)$request->input('current_reading', ''));
        $readingDate = clean($request->input('reading_date')) ?: date('Y-m-d');
        $remarks = clean($request->input('remarks'));

        $consumer = isValidMeterNumber($meter)
            ? DB::table('consumers')->where('meter_number', $meter)->first(['consumer_id', 'full_name', 'status', 'purok_id'])
            : null;

        $error = match (true) {
            !isValidMeterNumber($meter) => 'Meter Number must contain numbers only (e.g. 1001).',
            !$consumer => "No consumer has Meter Number $meter.",
            !$request->user()->canAccessPurok((int)$consumer->purok_id) => "Meter Number $meter is not in a purok assigned to you.",
            $consumer->status !== 'active' => "Meter Number $meter belongs to an account that is not active.",
            !preg_match('/^\d{4}-\d{2}$/', $period) => 'Please choose a valid billing period.',
            !is_numeric($previousRaw) || !is_numeric($currentRaw) => 'Readings must be numbers.',
            (float)$previousRaw < 0 || (float)$currentRaw < 0 => 'Readings cannot be negative.',
            (float)$currentRaw < (float)$previousRaw => 'Present Reading cannot be lower than the Previous Reading.',
            default => null,
        };
        if ($error) {
            return ['ok' => false, 'duplicate' => false, 'message' => $error];
        }

        $previous = (float)$previousRaw;
        $current = (float)$currentRaw;
        $userId = $request->user()->user_id;
        try {
            $bill = DB::transaction(function () use ($consumer, $period, $previous, $current, $readingDate, $remarks, $userId, $billing, $clientRef) {
                $reading = MeterReading::create([
                    'consumer_id' => $consumer->consumer_id, 'billing_period' => $period, 'previous_reading' => $previous,
                    'current_reading' => $current, 'reading_date' => $readingDate,
                    'recorded_by' => $userId, 'remarks' => $remarks ?: null, 'client_ref' => $clientRef,
                ]);

                $row = DB::table('meter_readings')->where('reading_id', $reading->reading_id)->first();
                return $billing->generateBillForReading($row, $userId);
            });
        } catch (QueryException) {
            return ['ok' => false, 'duplicate' => true, 'message' => "Meter $meter already has a reading for " . billingPeriodLabel($period) . '.'];
        }

        $previousBalance = $billing->unpaidBalance($consumer->consumer_id, $bill->bill_id);
        log_activity($userId, 'meter_reading_add', "Recorded reading for meter $meter ({$consumer->full_name}), " . billingPeriodLabel($period)
            . ': ' . number_format($previous, 2) . ' → ' . number_format($current, 2));
        return [
            'ok' => true, 'duplicate' => false, 'total' => (float)$bill->total_amount,
            'message' => "Saved for meter $meter ({$consumer->full_name}). Consumption: " . number_format($current - $previous, 2)
                . ' m³, Total: ' . formatCurrency($bill->total_amount)
                . ($previousBalance > 0 ? ', unpaid balance: ' . formatCurrency($previousBalance) : '')
                . '. The bill was sent to the administrator.',
        ];
    }

    public function update(Request $request, MeterReading $reading, BillingService $billing)
    {
        if (!$this->canManage($request, $reading)) {
            flash('danger', 'This reading is not in a purok assigned to you.');
            return back();
        }

        $previousRaw = trim((string)$request->input('previous_reading', ''));
        $currentRaw = trim((string)$request->input('current_reading', ''));

        if (!is_numeric($previousRaw) || !is_numeric($currentRaw) || (float)$previousRaw < 0) {
            flash('danger', 'Readings must be numbers.');
            return back();
        }
        if ((float)$currentRaw < (float)$previousRaw) {
            flash('danger', 'Present Reading cannot be lower than the Previous Reading.');
            return back();
        }

        $reading->update([
            'previous_reading' => (float)$previousRaw,
            'current_reading'  => (float)$currentRaw,
            'reading_date'     => clean($request->input('reading_date')) ?: $reading->reading_date,
            'remarks'          => clean($request->input('remarks')) ?: null,
        ]);
        $meter = DB::table('consumers')->where('consumer_id', $reading->consumer_id)->value('meter_number');
        log_activity($request->user()->user_id, 'meter_reading_edit', "Corrected the " . billingPeriodLabel($reading->billing_period) . " reading of meter $meter");

        $row = DB::table('meter_readings')->where('reading_id', $reading->reading_id)->first();
        match ($billing->recomputeBillForReading($row)) {
            'updated' => flash('success', 'Meter reading updated, and its bill was re-computed.'),
            'locked'  => flash('warning', 'Meter reading updated. Its bill already has payments, so the bill amount was NOT changed — adjust it manually if needed.'),
            default   => flash('success', 'Meter reading updated.'),
        };
        return back();
    }

    /**
     * Deletes a wrong reading together with its bill. Only the consumer's latest reading,
     * and only while its bill has no payments, so payment records and the reading chain stay intact.
     */
    public function destroy(Request $request, MeterReading $reading)
    {
        if (!$this->canManage($request, $reading)) {
            flash('danger', 'This reading is not in a purok assigned to you.');
            return back();
        }

        $meter = DB::table('consumers')->where('consumer_id', $reading->consumer_id)->value('meter_number');
        $label = billingPeriodLabel($reading->billing_period);
        $isLatest = !DB::table('meter_readings')->where('consumer_id', $reading->consumer_id)
            ->where('billing_period', '>', $reading->billing_period)->exists();
        $bill = DB::table('water_bills')->where('reading_id', $reading->reading_id)->first(['bill_id', 'amount_paid']);
        $hasPayments = $bill && ((float)$bill->amount_paid > 0 || DB::table('payments')->where('bill_id', $bill->bill_id)->exists());

        if (!$isLatest) {
            flash('danger', "Only the latest reading of meter $meter can be deleted. Correct this one with Edit instead.");
            return back();
        }
        if ($hasPayments) {
            flash('danger', "The $label bill of meter $meter already has payments, so its reading cannot be deleted. Use Edit to correct it.");
            return back();
        }

        DB::transaction(function () use ($reading, $bill) {
            if ($bill) {
                DB::table('water_bills')->where('bill_id', $bill->bill_id)->delete();
            }
            $reading->delete();
        });
        log_activity($request->user()->user_id, 'meter_reading_delete', "Deleted the $label reading of meter $meter and its bill");
        flash('success', "Deleted the $label reading of meter $meter and its bill.");
        return back();
    }

    private function canManage(Request $request, MeterReading $reading): bool
    {
        $purokId = DB::table('consumers')->where('consumer_id', $reading->consumer_id)->value('purok_id');
        return $request->user()->canAccessPurok((int)$purokId);
    }
}
