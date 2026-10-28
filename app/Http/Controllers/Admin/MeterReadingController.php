<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeterReading;
use App\Models\Purok;
use App\Services\ApplicationFiles;
use App\Services\BillingService;
use App\Services\MeterPhotoReader;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Meter readings. The meter reader records a reading by taking a photo of the
 * meter: the meter number and the reading are read from the photo
 * (MeterPhotoReader), never typed, and the photo is kept with the reading.
 */
class MeterReadingController extends Controller
{
    private const BILL_STATUSES = ['unpaid', 'partially_paid', 'paid', 'overdue'];

    /** stored_files folder of the meter photos. */
    public const PHOTO_FOLDER = 'meter-photos';

    /** How long a photo read in the form stays valid for saving (also covers saving on the phone, then sending later). */
    private const SCAN_TTL_DAYS = 3;

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

    /**
     * "Take Photo of Meter": stores the photo, reads the meter number and the reading from it,
     * checks them, and returns what the form shows (all read-only). What was read is kept on the
     * server with the photo — saving uses that, never numbers sent by the page.
     * With reading_id it is the photo for correcting that reading.
     */
    public function scan(Request $request, BillingService $billing, ApplicationFiles $files, MeterPhotoReader $reader)
    {
        $correcting = $request->filled('reading_id') ? MeterReading::find((int)$request->input('reading_id')) : null;
        if ($request->filled('reading_id') && (!$correcting || !$this->canManage($request, $correcting))) {
            return response()->json(['ok' => false, 'message' => 'This reading is not in a purok assigned to you.'], 403);
        }

        [$scan, $error, $retry] = $this->readPhoto($files, $reader, (string)$request->input('photo_data'));
        if (!$scan) {
            return response()->json(['ok' => false, 'retry' => $retry, 'message' => $error], $retry ? 503 : 422);
        }

        $period = $correcting?->billing_period ?? clean($request->input('billing_period'));
        $check = $this->checkScan($request, $billing, $scan, $period, clean($request->input('expect_meter')), $correcting);
        if ($check['error']) {
            $files->in(self::PHOTO_FOLDER)->delete($scan['photo']);
            return response()->json(['ok' => false, 'message' => $check['error']], 422);
        }

        Cache::put('meter-scan:' . $scan['photo'], [
            'user_id' => $request->user()->user_id, 'meter' => $check['meter'], 'reading' => $scan['reading'],
            'reading_id' => $correcting?->reading_id,
        ], now()->addDays(self::SCAN_TTL_DAYS));

        return response()->json(['ok' => true, 'photo' => $scan['photo']] + $check);
    }

    /** The photo a reading was read from (administrators, and the meter reader of its purok). */
    public function photo(Request $request, MeterReading $reading, ApplicationFiles $files)
    {
        abort_unless($request->user()->isAdmin() || $this->canManage($request, $reading), 403);
        return $files->in(self::PHOTO_FOLDER)->response($reading->photo_file, ['Cache-Control' => 'private, max-age=86400'], 'No photo for this reading.');
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
     * Saves one reading from its meter photo and generates its bill. The meter, the previous and
     * the present reading all come from the server (the photo and the last reading) — numbers
     * sent by the page are ignored. The photo is the one already read in the form (`photo`).
     * Returns ['ok', 'duplicate', 'message', 'total'].
     */
    private function saveReading(Request $request, BillingService $billing, ?string $clientRef = null): array
    {
        $files = app(ApplicationFiles::class);
        $period = clean($request->input('billing_period'));
        $readingDate = clean($request->input('reading_date')) ?: date('Y-m-d');
        $remarks = clean($request->input('remarks'));
        $userId = $request->user()->user_id;
        $fail = fn (string $message) => ['ok' => false, 'duplicate' => false, 'message' => $message];

        if (!preg_match('/^\d{4}-\d{2}$/', $period) || $period > currentBillingPeriod()) {
            return $fail('Please choose a valid billing period.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $readingDate) || $readingDate > date('Y-m-d')) {
            $readingDate = date('Y-m-d');
        }

        // The photo, already read in the form (Take Photo of Meter).
        $photo = (string)$request->input('photo');
        if ($photo === '') {
            return $fail('Take a photo of the meter to record its reading.');
        }
        $cached = Cache::get('meter-scan:' . $photo);
        if (!$cached || $cached['user_id'] !== $userId || $cached['reading_id'] !== null || !$files->in(self::PHOTO_FOLDER)->exists($photo)) {
            return $fail('This meter photo has expired. Please take the photo again.');
        }
        $scan = ['photo' => $photo, 'meter' => $cached['meter'], 'reading' => (float)$cached['reading']];

        $check = $this->checkScan($request, $billing, $scan, $period, clean($request->input('expect_meter')), null);
        if ($check['error']) {
            return $fail($check['error']);
        }
        $consumerId = $check['consumer_id'];
        $meter = $check['meter'];
        $previous = $check['previous_reading'];
        $current = $check['present_reading'];

        try {
            $bill = DB::transaction(function () use ($consumerId, $period, $previous, $current, $readingDate, $remarks, $userId, $billing, $clientRef, $scan) {
                $reading = MeterReading::create([
                    'consumer_id' => $consumerId, 'billing_period' => $period, 'previous_reading' => $previous,
                    'current_reading' => $current, 'reading_date' => $readingDate,
                    'recorded_by' => $userId, 'remarks' => $remarks ?: null, 'client_ref' => $clientRef,
                    'photo_file' => $scan['photo'],
                ]);

                $row = DB::table('meter_readings')->where('reading_id', $reading->reading_id)->first();
                return $billing->generateBillForReading($row, $userId);
            });
        } catch (QueryException) {
            return ['ok' => false, 'duplicate' => true, 'message' => "Meter $meter already has a reading for " . billingPeriodLabel($period) . '.'];
        }
        Cache::forget('meter-scan:' . $scan['photo']);

        $previousBalance = $billing->unpaidBalance($consumerId, $bill->bill_id);
        log_activity($userId, 'meter_reading_add', "Recorded reading for meter $meter ({$check['name']}), " . billingPeriodLabel($period)
            . ': ' . number_format($previous, 2) . ' → ' . number_format($current, 2) . ' (read from the meter photo)');
        return [
            'ok' => true, 'duplicate' => false, 'total' => (float)$bill->total_amount,
            'message' => "Saved for meter $meter ({$check['name']}). Consumption: " . number_format($current - $previous, 2)
                . ' m³, Total: ' . formatCurrency($bill->total_amount)
                . ($previousBalance > 0 ? ', unpaid balance: ' . formatCurrency($previousBalance) : '')
                . '. The bill was sent to the administrator.',
        ];
    }

    /**
     * Stores a meter photo (a JPEG/PNG/WEBP data URL from the phone) and reads it.
     * @return array{0: ?array{photo: string, meter: string, reading: float}, 1: ?string, 2: bool} [scan, error, try again later]
     */
    private function readPhoto(ApplicationFiles $files, MeterPhotoReader $reader, string $dataUrl): array
    {
        $store = $files->in(self::PHOTO_FOLDER);
        $this->removeUnusedPhotos();

        $errors = [];
        $name = $store->store(null, $dataUrl, 'meter', true, $errors);
        if (!$name) {
            return [null, $errors[0] ?? 'Take a photo of the meter.', false];
        }
        [$read, $error] = $reader->read((string)$store->contents($name));
        if (!$read) {
            $store->delete($name);
            return $error
                ? [null, $error, false]
                : [null, 'The photo could not be read right now. Check your internet and try again in a moment.', true];
        }
        return [['photo' => $name] + $read, null, false];
    }

    /** Photos read but never saved with a reading (form closed, photo retaken) are removed after a few days. */
    private function removeUnusedPhotos(): void
    {
        DB::table('stored_files')->where('folder', self::PHOTO_FOLDER)
            ->where('created_at', '<', now()->subDays(self::SCAN_TTL_DAYS + 1))
            ->whereNotIn('name', DB::table('meter_readings')->whereNotNull('photo_file')->select('photo_file'))
            ->delete();
    }

    /**
     * Checks what a photo said against AWAS: the meter exists, is the reader's, is the one the form
     * was opened for, isn't read yet this period, and the reading isn't lower than last month's.
     * Returns ['error' => ?string] plus, when fine, everything the form shows.
     */
    private function checkScan(Request $request, BillingService $billing, array $scan, string $period, string $expectMeter, ?MeterReading $correcting): array
    {
        $retake = ' Retake the photo so the meter number on the dial is clear.';
        $c = $this->consumerByMeter($scan['meter']);
        if (!$c) {
            return ['error' => "The photo shows meter number {$scan['meter']}, which is not in AWAS." . $retake];
        }
        $meter = $c->meter_number;
        if (!$request->user()->canAccessPurok((int)$c->purok_id)) {
            return ['error' => "Meter $meter ({$c->full_name}) is in {$c->purok_name}, which is not assigned to you."];
        }
        if ($correcting && (int)$correcting->consumer_id !== (int)$c->consumer_id) {
            return ['error' => "This photo is of meter $meter ({$c->full_name}), not the meter of the reading you are correcting." . $retake];
        }
        if ($expectMeter !== '' && ltrim($expectMeter, '0') !== ltrim($meter, '0')) {
            $expected = DB::table('consumers')->where('meter_number', $expectMeter)->value('full_name');
            return ['error' => "This photo is of meter $meter ({$c->full_name}), but you are recording meter $expectMeter"
                . ($expected ? " ($expected)" : '') . '. Take the photo of the right meter.'];
        }
        if (!$correcting && $c->status !== 'active') {
            return ['error' => "Meter $meter ({$c->full_name}) belongs to an account that is not active."];
        }
        if (!$correcting && DB::table('meter_readings')->where('consumer_id', $c->consumer_id)->where('billing_period', $period)->exists()) {
            return ['error' => "Meter $meter ({$c->full_name}) already has a reading for " . billingPeriodLabel($period) . '.'];
        }

        // Previous reading: the one before this period (when correcting, the reading's own).
        if ($correcting) {
            $previous = (float)$correcting->previous_reading;
            $lastPeriod = null;
        } else {
            $last = DB::table('meter_readings')->where('consumer_id', $c->consumer_id)->where('billing_period', '<', $period)
                ->orderByDesc('billing_period')->first(['current_reading', 'billing_period']);
            $previous = (float)($last->current_reading ?? $c->initial_meter_reading ?? 0);
            $lastPeriod = $last ? billingPeriodLabel($last->billing_period) : null;
        }
        $present = (float)$scan['reading'];
        if ($present < $previous) {
            return ['error' => 'The photo shows ' . number_format($present, 0, '.', '') . ' m³, which is lower than the previous reading ('
                . number_format($previous, 2) . ' m³). Retake the photo so all the black numbers are clear.'];
        }

        $usual = (float)DB::table('meter_readings')->where('consumer_id', $c->consumer_id)
            ->when($correcting, fn ($q) => $q->where('reading_id', '!=', $correcting->reading_id))
            ->orderByDesc('billing_period')->limit(3)->pluck('consumption')->avg();

        return [
            'error'             => null,
            'consumer_id'       => (int)$c->consumer_id,
            'meter'             => $meter,
            'name'              => $c->full_name,
            'purok'             => $c->purok_name,
            'consumer_type'     => consumerTypeLabel($c->consumer_type),
            'is_senior'         => (bool)$c->is_senior,
            'previous_reading'  => $previous,
            'present_reading'   => $present,
            'last_period'       => $lastPeriod,
            'usual_consumption' => round($usual, 2),
            'balance'           => $billing->unpaidBalance($c->consumer_id),
        ];
    }

    /** The consumer with this meter number; a leading 0 printed on the dial (or missing) still matches. */
    private function consumerByMeter(string $meter): ?object
    {
        $core = ltrim($meter, '0');
        if ($core === '') {
            return null;
        }
        return DB::table('consumers as c')->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->where('c.meter_number', 'like', "%$core")
            ->get(['c.consumer_id', 'c.meter_number', 'c.full_name', 'c.status', 'c.is_senior', 'c.consumer_type',
                'c.initial_meter_reading', 'c.purok_id', 'p.purok_name'])
            ->first(fn ($c) => ltrim($c->meter_number, '0') === $core);
    }

    public function update(Request $request, MeterReading $reading, BillingService $billing)
    {
        if (!$this->canManage($request, $reading)) {
            flash('danger', 'This reading is not in a purok assigned to you.');
            return back();
        }

        // A correction is a new photo of the same meter (read in the Correct Reading form): its reading
        // replaces the present reading. Numbers are never typed.
        $photo = (string)$request->input('photo');
        $cached = $photo !== '' ? Cache::get('meter-scan:' . $photo) : null;
        if (!$cached || $cached['user_id'] !== $request->user()->user_id || (int)$cached['reading_id'] !== (int)$reading->reading_id) {
            flash('danger', 'Take a new photo of the meter to correct this reading.');
            return back();
        }
        if ((float)$cached['reading'] < (float)$reading->previous_reading) {
            flash('danger', 'The new photo shows a reading lower than the previous reading. Please take the photo again.');
            return back();
        }

        $files = app(ApplicationFiles::class)->in(self::PHOTO_FOLDER);
        $oldPhoto = $reading->photo_file;
        $oldCurrent = (float)$reading->current_reading;
        $reading->update([
            'current_reading' => (float)$cached['reading'],
            'reading_date'    => date('Y-m-d'),
            'remarks'         => clean($request->input('remarks')) ?: null,
            'photo_file'      => $photo,
        ]);
        Cache::forget('meter-scan:' . $photo);
        if ($oldPhoto && $oldPhoto !== $photo) {
            $files->delete($oldPhoto);
        }
        $meter = DB::table('consumers')->where('consumer_id', $reading->consumer_id)->value('meter_number');
        log_activity($request->user()->user_id, 'meter_reading_edit', "Corrected the " . billingPeriodLabel($reading->billing_period)
            . " reading of meter $meter from a new photo: " . number_format($oldCurrent, 2) . ' → ' . number_format((float)$cached['reading'], 2));

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
