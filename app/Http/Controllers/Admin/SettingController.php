<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Services\DatabaseBackup;
use App\Services\Settings;
use Illuminate\Http\Request;

/**
 * System Settings: everything the billing engine uses, in one place —
 * general details, the water rate (minimum charge + excess tiers), due
 * dates and penalties, and discounts. Bills are computed from these only.
 */
class SettingController extends Controller
{
    /** Plain settings saved as they are (the water rate tiers are handled separately). */
    private const FIELDS = [
        'barangay_name', 'contact_email', 'contact_number', 'currency_symbol',
        'minimum_charge', 'minimum_cubic_meters', 'rates_effective_from',
        'due_day_of_month', 'overdue_grace_days', 'late_penalty_percent', 'disconnection_months',
        'senior_discount_percent',
    ];

    public function edit(BillingService $billing, Settings $settings, DatabaseBackup $backups)
    {
        $values = [];
        foreach (self::FIELDS as $key) {
            $values[$key] = $settings->get($key, '');
        }
        return view('admin.settings', [
            'values' => $values,
            'tiers'  => $billing->tariff()['tiers'],
        ] + ChatbotFaqController::sectionData($settings) + BackupController::sectionData($backups));
    }

    public function update(Request $request, Settings $settings)
    {
        $in = fn (string $key) => trim((string)$request->input($key, ''));
        $num = fn (string $key) => is_numeric($in($key)) ? (float)$in($key) : null;
        $whole = fn (string $key, int $min, int $max) => ctype_digit($in($key)) && (int)$in($key) >= $min && (int)$in($key) <= $max;

        // Excess tiers: each "To" must be above the one before it; only the last may be blank (no limit).
        $errors = [];
        $tiers = [];
        $lower = $num('minimum_cubic_meters') ?? 0;
        $tos = array_values((array)$request->input('tier_to', []));
        $rates = array_values((array)$request->input('tier_rate', []));
        foreach ($rates as $i => $rate) {
            $to = trim((string)($tos[$i] ?? ''));
            $isLast = $i === count($rates) - 1;
            $name = 'Tier ' . ($i + 2);
            if (!is_numeric($rate) || (float)$rate < 0) {
                $errors[] = "$name: enter a rate per m³ (0 or more).";
            }
            if ($to === '' && !$isLast) {
                $errors[] = "$name: only the last tier can have no \"To\" limit.";
            } elseif ($to !== '' && (!is_numeric($to) || (float)$to <= $lower)) {
                $errors[] = "$name: \"To\" must be more than " . rtrim(rtrim(number_format($lower, 2), '0'), '.') . ' m³.';
            }
            $tiers[] = ['to' => $to === '' ? null : (float)$to, 'rate' => round((float)$rate, 2)];
            $lower = $to === '' || !is_numeric($to) ? $lower : (float)$to;
        }

        $errors = array_merge(match (true) {
            $in('barangay_name') === '' => ['Enter the barangay name.'],
            $in('contact_email') !== '' && !filter_var($in('contact_email'), FILTER_VALIDATE_EMAIL) => ['The support email is not a valid email address.'],
            $in('currency_symbol') === '' => ['Enter the currency label.'],
            default => [],
        }, match (true) {
            $num('minimum_charge') === null || $num('minimum_charge') < 0 => ['Minimum charge must be 0 or more.'],
            $num('minimum_cubic_meters') === null || $num('minimum_cubic_meters') <= 0 => ['"Covers the first" must be more than 0 m³.'],
            !$tiers => ['Add at least one excess rate tier.'],
            !strtotime($in('rates_effective_from')) => ['Choose the date the rates take effect.'],
            default => [],
        }, $errors, match (true) {
            !$whole('due_day_of_month', 1, 31) => ['Due day must be a day of the month (1–31).'],
            !$whole('overdue_grace_days', 0, 90) => ['Grace period must be 0–90 days.'],
            $num('late_penalty_percent') === null || $num('late_penalty_percent') < 0 || $num('late_penalty_percent') > 100 => ['Late penalty must be 0–100%.'],
            !$whole('disconnection_months', 1, 24) => ['"Disconnect after" must be 1–24 months.'],
            $num('senior_discount_percent') === null || $num('senior_discount_percent') < 0 || $num('senior_discount_percent') > 100 => ['Senior citizen discount must be 0–100%.'],
            default => [],
        });
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        foreach (self::FIELDS as $key) {
            $settings->set($key, clean($in($key)));
        }
        $settings->set('excess_tiers', json_encode($tiers));
        $settings->set('excess_rate_per_cubic_meter', (string)$tiers[0]['rate']);   // kept for older screens

        log_activity($request->user()->user_id, 'settings_update', 'Updated system settings (water rates: '
            . formatCurrency((float)$in('minimum_charge')) . ' for the first ' . $in('minimum_cubic_meters') . ' m³, ' . count($tiers) . ' excess tier(s))');
        flash('success', 'System settings saved. New bills use these values; bills already made keep their amounts.');
        return redirect()->route('admin.settings.edit');
    }

    /** Maintenance mode on/off: while on, only administrators can use AWAS. */
    public function updateMaintenance(Request $request, Settings $settings)
    {
        $on = $request->input('maintenance_mode') === '1';
        $message = mb_substr(clean($request->input('maintenance_message')), 0, 500);
        $until = clean($request->input('maintenance_until'));
        if ($until !== '' && !strtotime($until)) {
            flash('danger', 'Please enter a valid date and time for when the system will be back.');
            return redirect()->to(route('admin.settings.edit') . '#maintenance');
        }

        $settings->set('maintenance_mode', $on ? '1' : '0');
        $settings->set('maintenance_message', $message);
        $settings->set('maintenance_until', $on ? $until : '');

        log_activity($request->user()->user_id, 'maintenance_mode', $on ? 'Turned ON maintenance mode' . ($until ? " (until $until)" : '') : 'Turned OFF maintenance mode');
        flash($on ? 'warning' : 'success', $on
            ? 'Maintenance mode is ON — consumers and meter readers now see the maintenance page and are signed out.'
            : 'Maintenance mode is OFF — everyone can use AWAS again.');
        return redirect()->to(route('admin.settings.edit') . '#maintenance');
    }
}
