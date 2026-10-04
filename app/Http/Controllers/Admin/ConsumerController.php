<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consumer;
use App\Models\Purok;
use App\Services\BillingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsumerController extends Controller
{
    private const STATUSES = ['active', 'disconnected', 'inactive'];

    public function index(Request $request)
    {
        $search = clean($request->query('q'));
        $purokFil = (int)$request->query('purok', 0);
        $statusFil = clean($request->query('status'));
        $typeFil = clean($request->query('type'));
        $allowed = $request->user()->assignedPurokIds();   // Meter Reader: only their puroks

        $consumers = Consumer::query()
            ->join('puroks as p', 'p.purok_id', '=', 'consumers.purok_id')
            ->select('consumers.*', 'p.purok_name')
            // Unpaid balance — still shown after an account is disconnected.
            ->selectRaw('(SELECT COALESCE(SUM(b.total_amount - b.amount_paid), 0) FROM water_bills b WHERE b.consumer_id = consumers.consumer_id AND b.status <> ? ) AS unpaid_balance', ['paid'])
            ->when($allowed !== null, fn ($q) => $q->whereIn('consumers.purok_id', $allowed ?: [0]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('consumers.full_name', like_operator(), "%$search%")
                ->orWhere('consumers.meter_number', like_operator(), "%$search%")
                ->orWhere('p.purok_name', like_operator(), "%$search%")))
            ->when($purokFil > 0, fn ($q) => $q->where('consumers.purok_id', $purokFil))
            ->when(in_array($statusFil, self::STATUSES, true), fn ($q) => $q->where('consumers.status', $statusFil))
            ->when(isset(Consumer::TYPES[$typeFil]), fn ($q) => $q->where('consumers.consumer_type', $typeFil))
            // Surname (last word of the name) first, as the list displays "Surname, Given".
            ->orderByRaw(DB::getDriverName() === 'pgsql'
                ? "reverse(split_part(reverse(regexp_replace(regexp_replace(split_part(consumers.full_name, ' / ', 1), '\\s*\\([^)]*\\)\$', ''), '\\s+(jr\\.?|sr\\.?|i{1,3}|iv|#\\d+)\$', '', 'i')), ' ', 1)) ASC, consumers.full_name ASC"
                : "SUBSTRING_INDEX(consumers.full_name, ' ', -1) ASC, consumers.full_name ASC")
            ->paginate(12)->withQueryString();

        // Meter Reader: latest reading of each listed consumer, to see who still needs this month's reading.
        $lastReadings = collect();
        if ($request->user()->isMeterReader()) {
            $lastReadings = DB::table('meter_readings')
                ->whereIn('consumer_id', $consumers->pluck('consumer_id'))
                ->orderBy('billing_period')
                ->get(['consumer_id', 'billing_period', 'current_reading'])
                ->keyBy('consumer_id');
        }

        return view('admin.consumers.index', [
            'consumers' => $consumers,
            'lastReadings' => $lastReadings,
            'currentPeriod' => date('Y-m'),
            'puroks'    => Purok::when($allowed !== null, fn ($q) => $q->whereIn('purok_id', $allowed ?: [0]))->orderBy('purok_name')->get(),
            'noPuroks'  => $allowed === [],
            'search'    => $search,
            'purokFil'  => $purokFil,
            'statusFil' => $statusFil,
            'typeFil'   => $typeFil,
        ]);
    }

    public function store(Request $request)
    {
        [$data, $error] = $this->validated($request);
        if ($error) {
            flash('danger', $error);
            return back();
        }

        Consumer::create($data + [
            'status'     => 'active',
            'created_by' => $request->user()->user_id,
        ]);
        log_activity($request->user()->user_id, 'consumer_add', "Added consumer {$data['full_name']} (meter {$data['meter_number']})");
        flash('success', "Consumer added successfully. Meter Number: {$data['meter_number']}");
        return back();
    }

    public function update(Request $request, Consumer $consumer)
    {
        [$data, $error] = $this->validated($request, $consumer->consumer_id);
        if ($error) {
            flash('danger', $error);
            return back();
        }

        $status = clean($request->input('status', 'active'));
        $consumer->update($data + ['status' => in_array($status, self::STATUSES, true) ? $status : 'active']);
        log_activity($request->user()->user_id, 'consumer_edit', "Edited consumer {$data['full_name']} (meter {$data['meter_number']})");
        flash('success', 'Consumer updated successfully.');
        return back();
    }

    public function destroy(Request $request, Consumer $consumer)
    {
        try {
            $consumer->delete();
            log_activity($request->user()->user_id, 'consumer_delete', "Deleted consumer {$consumer->full_name} (meter {$consumer->meter_number})");
            flash('success', 'Consumer record deleted.');
        } catch (QueryException $e) {
            flash('danger', 'Cannot delete: this consumer has existing meter readings or bills. Set the status to Inactive instead.');
        }
        return back();
    }

    public function history(Consumer $consumer, BillingService $billing)
    {
        $consumer->purok_name = DB::table('puroks')->where('purok_id', $consumer->purok_id)->value('purok_name');

        return view('admin.consumers.history', [
            'consumer' => $consumer,
            'balance'  => $billing->unpaidBalance($consumer->consumer_id),
            'readings' => DB::table('meter_readings')->where('consumer_id', $consumer->consumer_id)->orderByDesc('billing_period')->get(),
            'bills'    => DB::table('water_bills as b')->leftJoin('billing_rates as r', 'r.rate_id', '=', 'b.rate_id')
                ->where('b.consumer_id', $consumer->consumer_id)->orderByDesc('b.billing_period')
                ->get(['b.*', 'r.rate_name']),
            'payments' => DB::table('payments as p')->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
                ->where('p.consumer_id', $consumer->consumer_id)->orderByDesc('p.payment_date')
                ->get(['p.*', 'b.billing_period']),
        ]);
    }

    /**
     * Shared add/edit fields.
     * @return array{0: array, 1: ?string} [data, error message]
     */
    private function validated(Request $request, ?int $exceptId = null): array
    {
        $type = clean($request->input('consumer_type', 'residential'));
        $data = [
            'meter_number'    => clean($request->input('meter_number')),
            'full_name'       => clean($request->input('full_name')),
            'address'         => clean($request->input('address')),
            'purok_id'        => (int)$request->input('purok_id', 0),
            'consumer_type'   => $type,
            'contact_number'  => clean($request->input('contact_number')) ?: null,
            'email'           => clean($request->input('email')) ?: null,
            'connection_date' => clean($request->input('connection_date')) ?: null,
            'is_senior'       => $request->boolean('is_senior'),
        ];

        $error = match (true) {
            $data['full_name'] === '' || $data['address'] === '' || $data['purok_id'] <= 0 => 'Meter Number, name, address, and purok are required.',
            !isValidMeterNumber($data['meter_number']) => 'Meter Number must contain numbers only (e.g. 1001) — no letters, spaces, or "ADL-" prefix.',
            !isset(Consumer::TYPES[$type]) => 'Please select a valid Type of Consumer.',
            DB::table('consumers')->where('meter_number', $data['meter_number'])
                ->when($exceptId, fn ($q) => $q->where('consumer_id', '!=', $exceptId))->exists()
                => "Meter Number {$data['meter_number']} is already assigned to another consumer.",
            default => null,
        };
        return [$data, $error];
    }
}
