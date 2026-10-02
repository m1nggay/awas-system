<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meter Number becomes the consumer identifier:
 *   - consumers.meter_serial_number  -> consumers.meter_number (digits only, unique)
 *   - consumers.account_number (ADL-YYYY-NNNN) is removed
 *   - "Type of Consumer" (residential/commercial/institutional) on consumers and applications
 *   - membership_applications.liveness_status (blink check result)
 *   - water_bills.bill_number loses the "BILL-" prefix: "{meter number}-{YYYY-MM}"
 *
 * Every step checks the current state first, so it is safe on a fresh
 * database (already in the new shape) and on older ones. Works on
 * PostgreSQL and MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. meter_serial_number -> meter_number
        if (Schema::hasColumn('consumers', 'meter_serial_number') && !Schema::hasColumn('consumers', 'meter_number')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE consumers RENAME COLUMN meter_serial_number TO meter_number');
            } else {
                DB::statement('ALTER TABLE consumers CHANGE meter_serial_number meter_number VARCHAR(50) NULL');
            }
        }

        // 2. Keep digits only ("MTR-00123" -> "00123", "ADL-1001" -> "1001").
        //    A value that becomes empty, or clashes with another consumer's
        //    number, is cleared so an administrator can re-enter it.
        $seen = [];
        foreach (DB::table('consumers')->orderBy('consumer_id')->get(['consumer_id', 'meter_number']) as $c) {
            $digits = preg_replace('/\D+/', '', (string)$c->meter_number);
            $digits = $digits === '' ? null : substr($digits, 0, 20);
            if ($digits !== null && isset($seen[$digits])) {
                $digits = null;
            }
            if ($digits !== null) {
                $seen[$digits] = true;
            }
            if ($digits !== $c->meter_number) {
                DB::table('consumers')->where('consumer_id', $c->consumer_id)->update(['meter_number' => $digits]);
            }
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE consumers ALTER COLUMN meter_number TYPE VARCHAR(20)');
        } else {
            DB::statement('ALTER TABLE consumers MODIFY meter_number VARCHAR(20) NULL');
        }
        if (!$this->indexExists('consumers', 'consumers_meter_number_unique')) {
            Schema::table('consumers', fn (Blueprint $t) => $t->unique('meter_number', 'consumers_meter_number_unique'));
        }

        // 3. Account Number is no longer used anywhere.
        if (Schema::hasColumn('consumers', 'account_number')) {
            Schema::table('consumers', fn (Blueprint $t) => $t->dropColumn('account_number'));
        }

        // 4. Type of Consumer
        if (!Schema::hasColumn('consumers', 'consumer_type')) {
            Schema::table('consumers', fn (Blueprint $t) => $t->string('consumer_type', 20)->default('residential'));
        }
        if (!Schema::hasColumn('membership_applications', 'consumer_type')) {
            Schema::table('membership_applications', fn (Blueprint $t) => $t->string('consumer_type', 20)->default('residential'));
        }
        if (!Schema::hasColumn('membership_applications', 'liveness_status')) {
            Schema::table('membership_applications', fn (Blueprint $t) => $t->string('liveness_status', 20)->default('not_performed'));
        }

        // 5. Bill references without the "BILL-" prefix.
        $bills = DB::table('water_bills as b')
            ->join('consumers as c', 'c.consumer_id', '=', 'b.consumer_id')
            ->where('b.bill_number', 'like', 'BILL-%')
            ->orderBy('b.bill_id')
            ->get(['b.bill_id', 'b.billing_period', 'b.consumer_id', 'c.meter_number']);
        foreach ($bills as $b) {
            $ref = ($b->meter_number ?: 'C' . $b->consumer_id) . '-' . $b->billing_period;
            if (DB::table('water_bills')->where('bill_number', $ref)->exists()) {
                $ref .= '-' . $b->bill_id;
            }
            DB::table('water_bills')->where('bill_id', $b->bill_id)->update(['bill_number' => $ref]);
        }

        // 6. Chatbot answers that still talk about account / serial numbers.
        $faqFixes = [
            'How can I view my consumer information?' => 'Your meter number, address, purok, type of consumer, and connection status are all shown on the "My Profile" page.',
            'What should I do if I think my meter reading is incorrect?' => 'Please contact the Barangay Adlay water office with your meter number and the reading in question. Meter readings can only be corrected by authorized staff, not through this chatbot.',
            'How do I register?' => 'Go to the "Create Account" page and enter your meter number (numbers only), full name, and purok exactly as registered with the barangay. If they match an existing consumer record without a linked login, your account will be created.',
            'Where can I view my billing history?' => 'Open "Billing History" in your resident menu to see your past, fully paid water bills. Bills you still need to pay are under "Current Bills".',
            'Where can I see my current bill?' => 'Click "Current Bills" in your resident menu to see your unpaid bill(s) and to submit a payment.',
            'What is my current water bill?' => 'You can view your unpaid bills anytime on the "Current Bills" page in your resident dashboard. It shows your consumption, amount due, any penalties, and your remaining balance.',
        ];
        foreach ($faqFixes as $question => $answer) {
            DB::table('chatbot_faqs')->where('question', $question)->update(['answer' => $answer]);
        }
    }

    public function down(): void
    {
        // Not reversible: account numbers were removed and meter numbers normalized.
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            return DB::table('pg_indexes')->where('tablename', $table)->where('indexname', $index)->exists();
        }
        return !empty(DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$index]));
    }
};
