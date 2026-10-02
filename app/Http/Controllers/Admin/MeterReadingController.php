<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeterReading;
use App\Models\Purok;
use App\Services\BillingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Meter readings. The Meter Reader records them (Meter Number, previous and
 * present reading); consumption and the bill total are computed by the
 * system and the bill is sent to the administrator automatically. The
 * administrator sees the list and may correct a reading.
 */
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

        $readings = DB::table('meter_readings as mr')
            ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
            ->leftJoin('water_bills as b', 'b.reading_id', '=', 'mr.reading_id')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('c.full_name', like_operator(), "%$search%")
                ->orWhere('c.meter_number', like_operator(), "%$search%")))
            ->when($purokFil > 0, fn ($q) => $q->where('c.purok_id', $purokFil))
            ->when(in_array($statusFil, self::BILL_STATUSES, true), fn ($q) => $q->where('b.status', $statusFil))
            ->when($periodFil !== '', fn ($q) => $q->where('mr.billing_period', $periodFil))
            ->orderByDesc('mr.reading_date')->orderByDesc('mr.reading_id')
            ->select('mr.*', 'c.full_name', 'c.meter_number', 'b.total_amount as bill_total',
                'b.amount_paid as bill_paid', 'b.status as bill_status')
            ->paginate(15)->withQueryString();

        return view('admin.readings.index', [
            'readings'  => $readings,
            'puroks'    => Purok::orderBy('purok_name')->get(),
            'periods'   => DB::table('meter_readings')->distinct()->orderByDesc('billing_period')->pluck('billing_period'),
            'tariff'    => $billing->tariff(),
            'search'    => $search,
            'purokFil'  => $purokFil,
            'statusFil' => $statusFil,
            'periodFil' => $periodFil,
        ]);
    }

    /**
     * Meter Number lookup for the Meter Reader's form: who it belongs to, the
     * previous reading to start from, and any unpaid balance.
     */
    public function lookup(Request $request, BillingService $billing)
    {
        $meter = clean($request->query('meter'));
        if (!isValidMeterNumber($meter)) {
            return response()->json(['found' => false, 'message' => 'Meter Number must contain numbers only.']);
        }

        $c = DB::table('consumers as c')->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->where('c.meter_number', $meter)
            ->first(['c.consumer_id', 'c.full_name', 'c.status', 'c.is_senior', 'c.consumer_type', 'c.initial_meter_reading', 'p.purok_name']);
        if (!$c) {
            return response()->json(['found' => false, 'message' => "No consumer has Meter Number $meter."]);
        }

        $last = DB::table('meter_readings')->where('consumer_id', $c->consumer_id)
            ->orderByDesc('billing_period')->first(['current_reading', 'billing_period']);
        $period = clean($request->query('period'));

        return response()->json([
            'found'            => true,
            'active'           => $c->status === 'active',
            'name'             => $c->full_name,
            'purok'            => $c->purok_name,
            'consumer_type'    => consumerTypeLabel($c->consumer_type),
            'is_senior'        => (bool)$c->is_senior,
            'previous_reading' => (float)($last->current_reading ?? $c->initial_meter_reading ?? 0),
            'last_period'      => $last ? billingPeriodLabel($last->billing_period) : null,
            'balance'          => $billing->unpaidBalance($c->consumer_id),
            'already_read'     => $period !== '' && DB::table('meter_readings')
                ->where('consumer_id', $c->consumer_id)->where('billing_period', $period)->exists(),
        ]);
    }

    /** Recorded by the Meter Reader — the bill is generated automatically. */
    public function store(Request $request, BillingService $billing)
    {
        $meter = clean($request->input('meter_number'));
        $period = clean($request->input('billing_period'));
        $previousRaw = trim((string)$request->input('previous_reading', ''));
        $currentRaw = trim((string)$request->input('current_reading', ''));
        $readingDate = clean($request->input('reading_date')) ?: date('Y-m-d');
        $remarks = clean($request->input('remarks'));

        $consumer = isValidMeterNumber($meter)
            ? DB::table('consumers')->where('meter_number', $meter)->first(['consumer_id', 'full_name', 'status'])
            : null;

        $error = match (true) {
            !isValidMeterNumber($meter) => 'Meter Number must contain numbers only (e.g. 1001).',
            !$consumer => "No consumer has Meter Number $meter.",
            $consumer->status !== 'active' => "Meter Number $meter belongs to an account that is not active.",
            !preg_match('/^\d{4}-\d{2}$/', $period) => 'Please choose a valid billing period.',
            !is_numeric($previousRaw) || !is_numeric($currentRaw) => 'Readings must be numbers.',
            (float)$previousRaw < 0 || (float)$currentRaw < 0 => 'Readings cannot be negative.',
            (float)$currentRaw < (float)$previousRaw => 'Present Reading cannot be lower than the Previous Reading.',
            default => null,
        };
        if ($error) {
            flash('danger', $error);
            return back();
        }

        $previous = (float)$previousRaw;
        $current = (float)$currentRaw;
        $userId = $request->user()->user_id;
        try {
            // The reading and its bill are saved together: the bill goes
            // straight to the consumer and the administrators.
            $bill = DB::transaction(function () use ($consumer, $period, $previous, $current, $readingDate, $remarks, $userId, $billing) {
                $reading = MeterReading::create([
                    'consumer_id' => $consumer->consumer_id, 'billing_period' => $period, 'previous_reading' => $previous,
                    'current_reading' => $current, 'reading_date' => $readingDate,
                    'recorded_by' => $userId, 'remarks' => $remarks ?: null,
                ]);
                // Re-read so the database-computed consumption is included.
                $row = DB::table('meter_readings')->where('reading_id', $reading->reading_id)->first();
                return $billing->generateBillForReading($row, $userId);
            });
            $previousBalance = $billing->unpaidBalance($consumer->consumer_id, $bill->bill_id);
            log_activity($userId, 'meter_reading_add', "Recorded reading for meter $meter ({$consumer->full_name}), " . billingPeriodLabel($period)
                . ': ' . number_format($previous, 2) . ' → ' . number_format($current, 2));
            flash('success', "Saved for meter $meter ({$consumer->full_name}). Consumption: " . number_format($current - $previous, 2)
                . ' m³, Total: ' . formatCurrency($bill->total_amount)
                . ($previousBalance > 0 ? ', unpaid balance: ' . formatCurrency($previousBalance) : '')
                . '. The bill was sent to the administrator.');
        } catch (QueryException $e) {
            flash('danger', "Meter $meter already has a reading for " . billingPeriodLabel($period) . '.');
        }
        return back();
    }

    /** Admin-only correction of a recorded reading (its bill is updated too while unpaid). */
    public function update(Request $request, MeterReading $reading, BillingService $billing)
    {
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
            'reading_date'     => clean($request->input('reading_date')),
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
}
