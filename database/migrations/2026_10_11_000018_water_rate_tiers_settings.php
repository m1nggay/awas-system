<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Water rates now live in System Settings, where bills are computed from:
 *   - excess_tiers: JSON list of {"to": m³ or null for no limit, "rate": PHP per m³},
 *     each charging only the cubic meters that fall inside it
 *   - late_penalty_percent: penalty on overdue bills (was only on the unused
 *     billing_rates brackets, so it never applied)
 *   - rates_effective_from: date shown with the rates
 * Starts from what bills actually charged: one tier at the old excess rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $setting = fn (string $key) => DB::table('system_settings')->where('setting_key', $key)->value('setting_value');
        $activeRates = DB::table('billing_rates')->where('is_active', true);

        DB::table('system_settings')->insertOrIgnore([
            [
                'setting_key'   => 'excess_tiers',
                'setting_value' => json_encode([['to' => null, 'rate' => (float)($setting('excess_rate_per_cubic_meter') ?? 15)]]),
                'description'   => 'Excess rate tiers after the minimum: [{"to": m³ or null, "rate": PHP per m³}]',
            ],
            [
                'setting_key'   => 'late_penalty_percent',
                'setting_value' => (string)(float)((clone $activeRates)->max('penalty_percentage') ?? 0),
                'description'   => 'Penalty (% of the unpaid bill) added when a bill becomes overdue',
            ],
            [
                'setting_key'   => 'rates_effective_from',
                'setting_value' => (string)((clone $activeRates)->min('effective_date') ?? now()->toDateString()),
                'description'   => 'Date the current water rates took effect',
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('setting_key', ['excess_tiers', 'late_penalty_percent', 'rates_effective_from'])->delete();
    }
};
