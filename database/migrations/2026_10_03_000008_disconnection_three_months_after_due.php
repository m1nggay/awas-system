<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Disconnection now comes after 3 months of non-payment from the due date
 * (bills stay due on the 19th of the month after the billing month).
 * Replaces the old "days after due date" setting and updates unpaid bills.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->where('setting_key', 'disconnection_days')->delete();
        DB::table('system_settings')->insertOrIgnore([
            'setting_key' => 'disconnection_months', 'setting_value' => '3',
            'description' => 'Months of non-payment after the due date before service is subject to disconnection',
        ]);

        foreach (DB::table('water_bills')->where('status', '!=', 'paid')->get(['bill_id', 'due_date']) as $bill) {
            $due = new DateTimeImmutable(substr((string)$bill->due_date, 0, 10));
            $first = $due->modify('first day of this month')->modify('+3 months');
            $disc = $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min((int)$due->format('j'), (int)$first->format('t')));
            DB::table('water_bills')->where('bill_id', $bill->bill_id)->update(['disconnection_date' => $disc->format('Y-m-d')]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->where('setting_key', 'disconnection_months')->delete();
        DB::table('system_settings')->insertOrIgnore([
            'setting_key' => 'disconnection_days', 'setting_value' => '5',
            'description' => 'Days after the due date on which service is subject to disconnection',
        ]);
    }
};
