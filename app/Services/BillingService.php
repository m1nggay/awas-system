<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Billing rules and numbering carried over from includes/functions.php.
 * Consumers are identified by their Meter Number (digits only).
 */
class BillingService
{
    public function __construct(private Settings $settings)
    {
    }

    /**
     * Internal bill reference "{meter number}-{YYYY-MM}" (one bill per meter
     * per period). Screens show the Meter Number and period, not this value.
     */
    public function generateBillNumber(?string $meterNumber, string $billingPeriod, int $consumerId): string
    {
        $ref = ($meterNumber ?: 'C' . $consumerId) . '-' . $billingPeriod;
        if (DB::table('water_bills')->where('bill_number', $ref)->exists()) {
            $ref .= '-' . (DB::table('water_bills')->max('bill_id') + 1);
        }
        return $ref;
    }

    public function generateApplicationReference(): string
    {
        $year = date('Y');
        $count = DB::table('membership_applications')->where('reference_code', 'like', "APP-$year-%")->count() + 1;
        return sprintf('APP-%s-%04d', $year, $count);
    }

    public function generatePaymentReference(): string
    {
        $year = date('Y');
        $count = DB::table('payments')->where('payment_reference', 'like', "PMT-$year-%")->count() + 1;
        return sprintf('PMT-%s-%06d', $year, $count);
    }

    /** Tariff values the browser needs to preview a bill exactly like computeBillAmount(). */
    public function tariff(): array
    {
        return [
            'minimumCharge' => (float)$this->settings->get('minimum_charge', 150),
            'includedCum'   => (float)$this->settings->get('minimum_cubic_meters', 10),
            'excessRate'    => (float)$this->settings->get('excess_rate_per_cubic_meter', 15),
            'seniorPct'     => (float)$this->settings->get('senior_discount_percent', 20),
        ];
    }

    /**
     * Water bill computation (the official tariff in System Settings):
     *   1. Consumption      = present reading - previous reading (already on the reading row)
     *   2. Minimum charge   = always charged, even below the included cu. m.
     *   3. Excess cu. m.    = max(0, consumption - included cu. m.)
     *   4. Excess charge    = excess cu. m. * water rate
     *   5. Sub-total        = minimum charge + excess charge
     *   6. Senior discount  = sub-total * discount % (seniors only)
     *   7. Total payable    = sub-total - discount, rounded to the centavo
     *   8. Due date         = due day of the month after the billing month
     *   9. Disconnection    = N months of non-payment after the due date (default 3)
     */
    public function computeBillAmount(float $consumption, bool $isSenior, string $billingPeriod): array
    {
        $t = $this->tariff();
        $dueDay   = (int)$this->settings->get('due_day_of_month', 19);
        $discMonths = max(1, (int)$this->settings->get('disconnection_months', 3));

        $excessCum    = max(0, $consumption - $t['includedCum']);
        $excessCharge = round($excessCum * $t['excessRate'], 2);
        $subtotal     = round($t['minimumCharge'] + $excessCharge, 2);
        $discount     = $isSenior ? round($subtotal * $t['seniorPct'] / 100, 2) : 0.0;
        $total        = round($subtotal - $discount, 2);

        $nextMonth = (new DateTimeImmutable($billingPeriod . '-01'))->modify('+1 month');
        $dueDay    = max(1, min($dueDay, (int)$nextMonth->format('t')));
        $dueDate   = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('n'), $dueDay);
        $discDate  = $this->addMonths($dueDate, $discMonths);

        return [
            'minimum_charge'     => $t['minimumCharge'],
            'excess_cum'         => $excessCum,
            'excess_charge'      => $excessCharge,
            'amount_due'         => $subtotal,
            'discount_amount'    => $discount,
            'total_amount'       => $total,
            'due_date'           => $dueDate->format('Y-m-d'),
            'disconnection_date' => $discDate->format('Y-m-d'),
        ];
    }

    /** Same day N months later, kept inside the month (Jan 31 + 1 month = Feb 28/29). */
    public function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $first = $date->modify('first day of this month')->modify("+$months months");
        return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min((int)$date->format('j'), (int)$first->format('t')));
    }

    /** Outstanding amount on a consumer's unpaid bills (optionally leaving one bill out). */
    public function unpaidBalance(int $consumerId, ?int $exceptBillId = null): float
    {
        return round((float)DB::table('water_bills')
            ->where('consumer_id', $consumerId)
            ->where('status', '!=', 'paid')
            ->when($exceptBillId, fn ($q) => $q->where('bill_id', '!=', $exceptBillId))
            ->sum(DB::raw('total_amount - amount_paid')), 2);
    }

    /** Amount still unpaid from the consumer's bills BEFORE the given billing month (carried onto that month's bill). */
    public function previousBalance(int $consumerId, string $billingPeriod): float
    {
        return round((float)DB::table('water_bills')
            ->where('consumer_id', $consumerId)
            ->where('status', '!=', 'paid')
            ->where('billing_period', '<', $billingPeriod)
            ->sum(DB::raw('total_amount - amount_paid')), 2);
    }

    /**
     * Creates the bill for a meter reading using the configured tariff, then
     * notifies the resident (their new bill) and every active administrator.
     * Earlier unpaid bills are left untouched — they stay as separate balances.
     */
    public function generateBillForReading(object $reading, int $generatedBy): object
    {
        $consumer = DB::table('consumers')->where('consumer_id', $reading->consumer_id)
            ->first(['user_id', 'full_name', 'meter_number', 'is_senior']);
        $computed = $this->computeBillAmount((float)$reading->consumption, !empty($consumer->is_senior), $reading->billing_period);
        $billNumber = $this->generateBillNumber($consumer->meter_number, $reading->billing_period, (int)$reading->consumer_id);

        $billId = DB::table('water_bills')->insertGetId([
            'bill_number'        => $billNumber,
            'consumer_id'        => $reading->consumer_id,
            'reading_id'         => $reading->reading_id,
            'billing_period'     => $reading->billing_period,
            'consumption'        => $reading->consumption,
            'rate_id'            => null,
            'amount_due'         => $computed['amount_due'],
            'discount_amount'    => $computed['discount_amount'],
            'penalty_amount'     => 0,
            'total_amount'       => $computed['total_amount'],
            'bill_date'          => date('Y-m-d'),
            'due_date'           => $computed['due_date'],
            'disconnection_date' => $computed['disconnection_date'],
            'status'             => 'unpaid',
            'generated_by'       => $generatedBy,
        ], 'bill_id');

        $period = billingPeriodLabel($reading->billing_period);
        $previousBalance = $this->unpaidBalance((int)$reading->consumer_id, $billId);

        if ($consumer && $consumer->user_id) {
            $this->createNotification(
                (int)$consumer->user_id, $billId, 'New Water Bill Available',
                "Your water bill for $period (" . formatCurrency($computed['total_amount'])
                    . ') is now available. Due on ' . formatDate($computed['due_date']) . '.'
                    . ($previousBalance > 0 ? ' You also have an unpaid balance of ' . formatCurrency($previousBalance) . '.' : ''),
                'bill_due'
            );
        }

        $recordedBy = DB::table('users')->where('user_id', $generatedBy)->value('full_name');
        foreach (DB::table('users')->where('role', 'admin')->where('status', 'active')->pluck('user_id') as $adminId) {
            $this->createNotification(
                (int)$adminId, $billId, 'New Water Bill Generated',
                "Meter {$consumer->meter_number} — {$consumer->full_name}, $period: "
                    . number_format((float)$reading->previous_reading, 2) . ' → ' . number_format((float)$reading->current_reading, 2)
                    . ' (' . number_format((float)$reading->consumption, 2) . ' m³), total ' . formatCurrency($computed['total_amount'])
                    . ($previousBalance > 0 ? ', previous balance ' . formatCurrency($previousBalance) : '')
                    . ". Recorded by $recordedBy.",
                'bill_due'
            );
        }

        log_activity($generatedBy, 'bill_generate', "Generated the $period bill for meter {$consumer->meter_number} (" . formatCurrency($computed['total_amount']) . ')');
        return DB::table('water_bills')->where('bill_id', $billId)->first();
    }

    /**
     * After an admin corrects a reading: re-computes its bill, but only while
     * nothing has been paid on it yet. Returns 'updated', 'none' (no bill) or
     * 'locked' (payments already applied — leave the bill untouched).
     */
    public function recomputeBillForReading(object $reading): string
    {
        $bill = DB::table('water_bills')->where('reading_id', $reading->reading_id)->first();
        if (!$bill) {
            return 'none';
        }
        $hasPayments = (float)$bill->amount_paid > 0
            || DB::table('payments')->where('bill_id', $bill->bill_id)->whereIn('status', ['pending', 'verified'])->exists();
        if ($hasPayments) {
            return 'locked';
        }

        $isSenior = (bool)DB::table('consumers')->where('consumer_id', $reading->consumer_id)->value('is_senior');
        $computed = $this->computeBillAmount((float)$reading->consumption, $isSenior, $reading->billing_period);
        DB::table('water_bills')->where('bill_id', $bill->bill_id)->update([
            'consumption'     => $reading->consumption,
            'amount_due'      => $computed['amount_due'],
            'discount_amount' => $computed['discount_amount'],
            'penalty_amount'  => 0,
            'total_amount'    => $computed['total_amount'],
            'status'          => 'unpaid',
        ]);
        // Re-applies the overdue penalty if the (unchanged) due date has passed.
        $this->refreshOverdueBills();
        return 'updated';
    }

    /** Flags unpaid bills past due date + grace period as overdue and applies the rate's penalty. */
    public function refreshOverdueBills(): void
    {
        $graceDays = (int)$this->settings->get('overdue_grace_days', 5);

        $overdue = DB::table('water_bills as b')
            ->leftJoin('billing_rates as r', 'r.rate_id', '=', 'b.rate_id')
            ->whereIn('b.status', ['unpaid', 'partially_paid'])
            // due_date + grace days < today  <=>  due_date < today - grace days
            ->where('b.due_date', '<', now()->subDays($graceDays)->toDateString())
            ->select('b.bill_id', 'b.amount_due', 'b.discount_amount', 'r.penalty_percentage')
            ->get();

        foreach ($overdue as $bill) {
            $penaltyPct = (float)($bill->penalty_percentage ?? 0);
            $payable = (float)$bill->amount_due - (float)$bill->discount_amount;
            $penalty = round($payable * ($penaltyPct / 100), 2);

            DB::table('water_bills')->where('bill_id', $bill->bill_id)->update([
                'status'         => 'overdue',
                'penalty_amount' => $penalty,
                'total_amount'   => round($payable + $penalty, 2),
            ]);
        }
    }

    public function createNotification(int $userId, ?int $billId, string $title, string $message, string $type = 'general'): void
    {
        DB::table('notifications')->insert([
            'user_id' => $userId, 'bill_id' => $billId, 'title' => $title, 'message' => $message, 'type' => $type,
        ]);
    }

    /**
     * Applies a verified payment amount to a bill: updates amount_paid and
     * recalculates status, then notifies the resident. Shared by manual
     * staff recording/verification and automatic PayMongo confirmation.
     */
    public function applyPaymentToBill(int $billId, float $amount): void
    {
        $bill = DB::table('water_bills')->where('bill_id', $billId)->first();
        if (!$bill) return;

        $newPaid = round((float)$bill->amount_paid + $amount, 2);
        $status = $bill->status;
        if ($newPaid >= (float)$bill->total_amount) {
            $status = 'paid';
        } elseif ($newPaid > 0) {
            $status = $bill->status === 'overdue' ? 'overdue' : 'partially_paid';
        }

        DB::table('water_bills')->where('bill_id', $billId)->update(['amount_paid' => $newPaid, 'status' => $status]);

        $userId = DB::table('consumers')->where('consumer_id', $bill->consumer_id)->value('user_id');
        if ($userId) {
            $this->createNotification(
                (int)$userId, $billId, 'Payment Received',
                'We received your payment of ' . formatCurrency($amount) . ' for your ' . billingPeriodLabel($bill->billing_period) . ' water bill. Thank you!',
                'payment_received'
            );
        }
    }

    public const REPORT_TYPES = [
        'purok'           => 'Billing Report by Purok',
        'consumption'     => 'Monthly Water Consumption',
        'billing_history' => 'All Bills (every period)',
        'paid'            => 'Paid Bills',
        'unpaid'          => 'Unpaid Bills',
        'overdue'         => 'Overdue Bills',
        'collection'      => 'Payment Collection',
        'consumers'       => 'Consumer Records',
        'meter_readings'  => 'Meter Reading Records',
    ];

    /** Reports the Meter Reader may open (reading-related, no payment collection data). */
    public const METER_READER_REPORTS = ['purok', 'consumption', 'meter_readings'];

    /**
     * Billing report grouped by Purok for one billing period:
     * [ ['purok' => 'Purok 1', 'rows' => [ {meter_number, full_name, present, previous,
     *    consumption, total, balance, status, date_paid}, ... ]], ... ]
     */
    public function getPurokReport(string $period, int $purokId = 0, ?array $onlyPuroks = null): array
    {
        $rows = DB::table('water_bills as b')
            ->join('consumers as c', 'c.consumer_id', '=', 'b.consumer_id')
            ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->leftJoin('meter_readings as mr', 'mr.reading_id', '=', 'b.reading_id')
            ->where('b.billing_period', $period)
            ->when($onlyPuroks !== null, fn ($q) => $q->whereIn('c.purok_id', $onlyPuroks ?: [0]))
            ->when($purokId > 0, fn ($q) => $q->where('c.purok_id', $purokId))
            ->orderBy('p.purok_name')->orderBy('c.full_name')
            ->select('b.bill_id', 'p.purok_name', 'c.meter_number', 'c.full_name', 'mr.current_reading', 'mr.previous_reading',
                'b.consumption', 'b.total_amount', 'b.amount_paid', 'b.status')
            ->get();

        $datePaid = DB::table('payments')
            ->whereIn('bill_id', $rows->pluck('bill_id'))->where('status', 'verified')
            ->groupBy('bill_id')->selectRaw('bill_id, MAX(payment_date) AS paid_on')
            ->pluck('paid_on', 'bill_id');

        $groups = [];
        foreach ($rows as $r) {
            $groups[$r->purok_name][] = (object)[
                'meter_number' => $r->meter_number,
                'full_name'    => $r->full_name,
                'present'      => (float)$r->current_reading,
                'previous'     => (float)$r->previous_reading,
                'consumption'  => (float)$r->consumption,
                'total'        => (float)$r->total_amount,
                'balance'      => max(0, round((float)$r->total_amount - (float)$r->amount_paid, 2)),
                'status'       => $r->status,
                'date_paid'    => $r->status === 'paid' && isset($datePaid[$r->bill_id]) ? substr($datePaid[$r->bill_id], 0, 10) : null,
            ];
        }

        return array_map(fn ($purok, $items) => ['purok' => $purok, 'rows' => $items], array_keys($groups), $groups);
    }

    /**
     * $onlyPuroks limits the consumption and meter-reading reports to those
     * puroks (a Meter Reader's assignment); null = all puroks.
     *
     * @return array{columns: string[], rows: array<int, array>}
     */
    public function getReportData(string $type, string $period = '', ?array $onlyPuroks = null): array
    {
        $limit = fn ($q) => $onlyPuroks === null ? $q : $q->whereIn('c.purok_id', $onlyPuroks ?: [0]);
        $columns = [];
        $rows = collect();

        switch ($type) {
            case 'consumption':
                $columns = ['Meter Number', 'Name', 'Purok', 'Previous Reading', 'Present Reading', 'Consumption (m3)', 'Reading Date'];
                $rows = DB::table('meter_readings as mr')
                    ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
                    ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
                    ->where('mr.billing_period', $period)
                    ->tap($limit)
                    ->orderBy('c.full_name')
                    ->get(['c.meter_number', 'c.full_name', 'p.purok_name', 'mr.previous_reading', 'mr.current_reading', 'mr.consumption', 'mr.reading_date']);
                break;

            case 'billing_history':
                $columns = ['Meter Number', 'Name', 'Period', 'Consumption', 'Total', 'Balance', 'Status', 'Due Date'];
                $rows = DB::table('water_bills as b')
                    ->join('consumers as c', 'c.consumer_id', '=', 'b.consumer_id')
                    ->orderByDesc('b.billing_period')->orderBy('c.full_name')
                    ->selectRaw('c.meter_number, c.full_name, b.billing_period, b.consumption, b.total_amount, b.total_amount - b.amount_paid AS balance, b.status, b.due_date')
                    ->get();
                break;

            case 'paid':
            case 'unpaid':
            case 'overdue':
                $columns = ['Meter Number', 'Name', 'Period', 'Total', 'Amount Paid', 'Balance', 'Due Date'];
                $rows = DB::table('water_bills as b')
                    ->join('consumers as c', 'c.consumer_id', '=', 'b.consumer_id')
                    ->where('b.status', $type)
                    ->orderBy('b.due_date')
                    ->selectRaw('c.meter_number, c.full_name, b.billing_period, b.total_amount, b.amount_paid, b.total_amount - b.amount_paid AS balance, b.due_date')
                    ->get();
                break;

            case 'collection':
                $columns = ['Reference', 'Meter Number', 'Name', 'Period', 'Amount', 'Method', 'Paid At', 'GCash Ref. No.', 'Status', 'Date'];
                $rows = DB::table('payments as p')
                    ->join('consumers as c', 'c.consumer_id', '=', 'p.consumer_id')
                    ->join('water_bills as b', 'b.bill_id', '=', 'p.bill_id')
                    ->orderByDesc('p.payment_date')
                    ->get(['p.payment_reference', 'c.meter_number', 'c.full_name', 'b.billing_period', 'p.amount_paid', 'p.payment_method',
                        'p.channel', 'p.payment_gateway_txn_id', 'p.status', 'p.payment_date'])
                    ->map(function ($r) {
                        $r->payment_method = paymentMethodLabel($r->payment_method);
                        $r->channel = paymentChannelLabel($r->channel);
                        $r->status = paymentStatusLabel($r->status);
                        return $r;
                    });
                break;

            case 'consumers':
                $columns = ['Meter Number', 'Name', 'Purok', 'Type of Consumer', 'Address', 'Contact', 'Status', 'Connection Date'];
                $rows = DB::table('consumers as c')
                    ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
                    ->orderBy('c.full_name')
                    ->get(['c.meter_number', 'c.full_name', 'p.purok_name', 'c.consumer_type', 'c.address', 'c.contact_number', 'c.status', 'c.connection_date'])
                    ->map(function ($r) {
                        $r->consumer_type = consumerTypeLabel($r->consumer_type);
                        return $r;
                    });
                break;

            case 'meter_readings':
                $columns = ['Meter Number', 'Name', 'Period', 'Previous Reading', 'Present Reading', 'Consumption', 'Reading Date', 'Recorded By'];
                $rows = DB::table('meter_readings as mr')
                    ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
                    ->join('users as u', 'u.user_id', '=', 'mr.recorded_by')
                    ->tap($limit)
                    ->orderByDesc('mr.reading_date')
                    ->get(['c.meter_number', 'c.full_name', 'mr.billing_period', 'mr.previous_reading', 'mr.current_reading', 'mr.consumption', 'mr.reading_date', 'u.full_name as staff_name']);
                break;
        }

        return ['columns' => $columns, 'rows' => $rows->map(fn ($r) => array_values((array)$r))->all()];
    }
}
