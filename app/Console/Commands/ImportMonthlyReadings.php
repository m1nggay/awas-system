<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enters a month of paper meter readings, the way the barangay does it:
 *   1. Meter Reader records every reading (bill is generated as usual).
 *   2. Administrator marks the red rows Inactive and records the green rows as paid in cash.
 *
 * CSV columns: purok, no, name, meter, present, previous, color (red|green|blank), date_paid (m/d/y, optional)
 *
 *   php artisan awas:import-readings database/data/readings-2026-08-purok1.csv --period=2026-08 --dry-run
 *   php artisan awas:import-readings database/data/readings-2026-08-purok1.csv --period=2026-08
 *
 * Safe to run again: readings already saved for the period, paid bills and inactive accounts are skipped.
 */
class ImportMonthlyReadings extends Command
{
    protected $signature = 'awas:import-readings {file} {--period= : Billing period YYYY-MM}
        {--reading-date= : Date the meters were read (default: last day of the period)}
        {--paid-date= : Payment date when the sheet has none (default: the bill due date)}
        {--dry-run : Show what would happen, then undo everything}';

    protected $description = 'Import a month of meter readings, deactivations (red) and cash payments (green) from a CSV';

    private array $log = [];

    public function handle(BillingService $billing, PaymentService $payments): int
    {
        $period = (string)$this->option('period');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Use --period=YYYY-MM');
            return self::FAILURE;
        }
        $rows = $this->readCsv($this->argument('file'));
        if ($rows === null) {
            return self::FAILURE;
        }
        $readingDate = $this->option('reading-date') ?: date('Y-m-t', strtotime("$period-01"));
        $dryRun = (bool)$this->option('dry-run');

        // Backfilled bills are months old: no emails to residents (they still see them in the app).
        config(['agas.brevo.api_key' => '']);

        $adminId = (int)DB::table('users')->where('role', 'admin')->where('status', 'active')->orderBy('user_id')->value('user_id');
        if (!$adminId) {
            $this->error('No active administrator account.');
            return self::FAILURE;
        }
        $puroks = DB::table('puroks')->pluck('purok_id', 'purok_name');
        $readers = DB::table('meter_reader_puroks as mp')->join('users as u', 'u.user_id', '=', 'mp.user_id')
            ->where('u.status', 'active')->pluck('mp.user_id', 'mp.purok_id');
        $firstNotification = (int)DB::table('notifications')->max('notification_id');

        $count = ['created' => 0, 'read' => 0, 'deactivated' => 0, 'paid' => 0];
        $seenConsumers = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $r) {
                $label = "{$r['purok']} #{$r['no']} {$r['name']} ({$r['meter']})";
                $purokId = $puroks[$r['purok']] ?? null;
                if (!$purokId) {
                    $this->warnRow($label, "purok \"{$r['purok']}\" not found — skipped");
                    continue;
                }
                // 1. Consumer: by meter number (name must match), else by name in the purok, else add them.
                $consumer = $this->findConsumer($r, (int)$purokId);
                if ($consumer === false) {
                    $owner = DB::table('consumers')->where('meter_number', $r['meter'])->value('full_name');
                    $this->warnRow($label, "meter number belongs to $owner in the system, and no one in the purok has this name — skipped");
                    continue;
                }
                if ($consumer && isset($seenConsumers[$consumer->consumer_id])) {
                    $this->warnRow($label, "same account as {$seenConsumers[$consumer->consumer_id]} on the sheet — skipped");
                    continue;
                }
                if (!$consumer) {
                    if (!isValidMeterNumber($r['meter']) || DB::table('consumers')->where('meter_number', $r['meter'])->exists()) {
                        $this->warnRow($label, 'not in the system and no valid meter number — skipped');
                        continue;
                    }
                    $id = DB::table('consumers')->insertGetId([
                        'full_name' => $this->displayName($r['name']), 'address' => 'Adlay Carrascal Surigao Del Sur', 'purok_id' => $purokId,
                        'meter_number' => $r['meter'], 'consumer_type' => 'residential', 'is_senior' => false,
                        'status' => 'active', 'meter_status' => 'active', 'connection_date' => '2026-07-30',
                        'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
                    ], 'consumer_id');
                    $consumer = DB::table('consumers')->where('consumer_id', $id)->first();
                    $count['created']++;
                    $this->log[] = "ADD    $label";
                } elseif ((int)$consumer->purok_id !== (int)$purokId) {
                    $this->warnRow($label, "meter belongs to {$consumer->full_name} in another purok — skipped");
                    continue;
                }
                $seenConsumers[$consumer->consumer_id] = $r['name'];

                // 2. Meter Reader records the reading → bill.
                $bill = DB::table('water_bills')->where('consumer_id', $consumer->consumer_id)->where('billing_period', $period)->first();
                if (!$bill && is_numeric($r['present']) && is_numeric($r['previous'])) {
                    if ((float)$r['present'] < (float)$r['previous']) {
                        $this->warnRow($label, "present {$r['present']} is lower than previous {$r['previous']} — reading skipped");
                    } else {
                        $readerId = (int)($readers[$purokId] ?? $adminId);
                        $readingId = DB::table('meter_readings')->insertGetId([
                            'consumer_id' => $consumer->consumer_id, 'billing_period' => $period,
                            'previous_reading' => (float)$r['previous'], 'current_reading' => (float)$r['present'],
                            'reading_date' => $readingDate, 'recorded_by' => $readerId, 'remarks' => 'Entered from the monthly reading sheet',
                        ], 'reading_id');
                        $reading = DB::table('meter_readings')->where('reading_id', $readingId)->first();
                        $bill = $billing->generateBillForReading($reading, $readerId);
                        DB::table('water_bills')->where('bill_id', $bill->bill_id)->update(['bill_date' => $readingDate]);
                        log_activity($readerId, 'meter_reading_add', "Recorded reading for meter {$consumer->meter_number} ({$consumer->full_name}), "
                            . billingPeriodLabel($period) . ': ' . number_format((float)$r['previous'], 2) . ' → ' . number_format((float)$r['present'], 2));
                        $count['read']++;
                        $this->log[] = "READ   $label  {$r['previous']} → {$r['present']} = " . formatCurrency($bill->total_amount);
                    }
                }

                // 3a. Red: account is inactive.
                if ($r['color'] === 'red' && $consumer->status !== 'inactive') {
                    DB::table('consumers')->where('consumer_id', $consumer->consumer_id)->update(['status' => 'inactive', 'updated_at' => now()]);
                    log_activity($adminId, 'consumer_deactivate', "Set {$consumer->full_name} (meter {$consumer->meter_number}) to Inactive");
                    $count['deactivated']++;
                    $this->log[] = "INACT  $label";
                }

                // 3b. Green: the whole bill was paid in cash at the barangay.
                if ($r['color'] === 'green') {
                    if (!$bill) {
                        $this->warnRow($label, 'marked paid but has no ' . billingPeriodLabel($period) . ' reading/bill — nothing to pay');
                    } elseif ($bill->status !== 'paid' && $payments->balance($bill) > 0) {
                        $amount = $payments->balance($bill);
                        [$payment, $error] = $payments->recordAtCounter((int)$bill->bill_id, 'cash', $amount, '', null,
                            'Paid in cash at the barangay (from the monthly reading sheet)', $adminId);
                        if ($error) {
                            $this->warnRow($label, "payment not recorded: $error");
                        } else {
                            $paidOn = $this->paidDate($r['date_paid']) ?? ($this->option('paid-date') ?: $bill->due_date);
                            DB::table('payments')->where('payment_id', $payment->payment_id)
                                ->update(['payment_date' => "$paidOn 09:00:00", 'verified_at' => "$paidOn 09:00:00"]);
                            $count['paid']++;
                            $this->log[] = "PAID   $label  " . formatCurrency($amount) . " on $paidOn";
                        }
                    }
                }
            }

            // Admins entered these themselves — don't flood their bell.
            DB::table('notifications')->where('notification_id', '>', $firstNotification)
                ->whereIn('user_id', DB::table('users')->whereIn('role', ['admin', 'staff'])->pluck('user_id'))
                ->update(['is_read' => true]);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Stopped, nothing was saved: ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($this->log as $line) {
            $this->line($line);
        }
        $this->newLine();
        $this->info(($dryRun ? 'DRY RUN (nothing saved): ' : 'Saved: ')
            . "{$count['created']} consumers added, {$count['read']} readings, {$count['deactivated']} set Inactive, {$count['paid']} cash payments.");
        return self::SUCCESS;
    }

    /**
     * By meter number when the name on that account matches the sheet, else by name in the purok.
     * Returns false when the meter number belongs to someone else and no one in the purok has the name.
     */
    private function findConsumer(array $r, int $purokId): object|false|null
    {
        $byMeter = isValidMeterNumber($r['meter']) ? DB::table('consumers')->where('meter_number', $r['meter'])->first() : null;
        if ($byMeter && $this->sameName($r['name'], $byMeter->full_name)) {
            return $byMeter;
        }
        $matches = DB::table('consumers')->where('purok_id', $purokId)->get()
            ->filter(fn ($c) => $this->sameName($r['name'], $c->full_name));
        if ($matches->count() === 1) {
            return $matches->first();
        }
        return $byMeter ? false : null;
    }

    /**
     * Same surname and at least one other name in common ("ACEVEDO, JERSON" = "Jerson Acevedo").
     * Longer words may differ by one letter ("ARREZA" = "Arrreza").
     */
    private function sameName(string $sheetName, string $systemName): bool
    {
        $words = fn ($s) => array_values(array_diff(preg_split('/[^a-zñ]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY),
            ['jr', 'sr', 'i', 'ii', 'iii', 'iv', 'ma', 'mrs', 'mr']));
        $close = fn ($a, $b) => $a === $b || (strlen($a) >= 5 && levenshtein($a, $b) <= 1);
        $shared = fn (array $mine, array $theirs) => array_filter($mine, fn ($w) => (bool)array_filter($theirs, fn ($t) => $close($w, $t)));

        $surname = $words(explode(',', $sheetName)[0]);
        $sheet = $words($sheetName);
        $system = $words($systemName);
        if (!$surname || !$shared($surname, $system)) {
            return false;
        }
        $given = array_diff($sheet, $surname);
        return !$given || (bool)$shared($given, $system);
    }

    /** "ACEVEDO, JERSON" → "Jerson Acevedo"; "CALIWATAN, ANGEL, JR." → "Angel Caliwatan Jr." */
    private function displayName(string $sheetName): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $sheetName)), 'strlen'));
        if (count($parts) >= 2) {
            $suffix = count($parts) > 2 && preg_match('/^(JR|SR|II|III|IV)\.?$/i', end($parts)) ? array_pop($parts) : null;
            $last = array_shift($parts);
            $name = implode(' ', $parts) . ' ' . $last . ($suffix ? ' ' . $suffix : '');
        } else {
            $name = $parts[0] ?? $sheetName;
        }
        $name = mb_convert_case(preg_replace('/\s+/', ' ', $name), MB_CASE_TITLE);
        return preg_replace_callback('/\b(Ii|Iii|Iv)\b/', fn ($m) => strtoupper($m[1]), $name);
    }

    private function paidDate(string $value): ?string
    {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', trim($value), $m)) {
            $year = strlen($m[3]) === 2 ? 2000 + (int)$m[3] : (int)$m[3];
            return checkdate((int)$m[1], (int)$m[2], $year) ? sprintf('%04d-%02d-%02d', $year, $m[1], $m[2]) : null;
        }
        return null;
    }

    private function warnRow(string $label, string $message): void
    {
        $this->log[] = "SKIP   $label: $message";
    }

    private function readCsv(string $file): ?array
    {
        $path = is_file($file) ? $file : base_path($file);
        if (!is_file($path) || !($fh = fopen($path, 'r'))) {
            $this->error("File not found: $file");
            return null;
        }
        $header = fgetcsv($fh);
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) < count($header)) {
                continue;
            }
            $row = array_map('trim', array_combine($header, array_slice($line, 0, count($header))));
            $row['meter'] = preg_replace('/\D/', '', $row['meter']);
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }
}
