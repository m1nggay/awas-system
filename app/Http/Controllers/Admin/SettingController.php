<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GcashQr;
use App\Services\Settings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const EDITABLE = [
        'barangay_name'               => 'Barangay Name',
        'minimum_charge'              => 'Minimum Charge (PHP, always charged)',
        'minimum_cubic_meters'        => 'Cubic Meters Covered by Minimum Charge',
        'excess_rate_per_cubic_meter' => 'Excess Rate (PHP per cu. m. beyond the minimum)',
        'senior_discount_percent'     => 'Senior Citizen Discount (%)',
        'due_day_of_month'            => 'Due Day (day of the month after the billing month)',
        'disconnection_days'          => 'Disconnection Date (days after due date)',
        'overdue_grace_days'          => 'Overdue Grace Period (days after due date)',
        'currency_symbol'             => 'Currency Label',
        'contact_email'               => 'Support Email',
        'contact_number'              => 'Support Contact Number',
    ];

    public function edit(Settings $settings, GcashQr $qr)
    {
        $values = [];
        foreach (array_keys(self::EDITABLE) as $key) {
            $values[$key] = $settings->get($key, '');
        }
        return view('admin.settings', ['fields' => self::EDITABLE, 'values' => $values, 'qr' => $qr]);
    }

    public function update(Request $request, Settings $settings)
    {
        foreach (array_keys(self::EDITABLE) as $key) {
            if ($request->has($key)) {
                $settings->set($key, clean($request->input($key)));
            }
        }
        log_activity($request->user()->user_id, 'settings_update', 'Updated system settings');
        flash('success', 'System settings updated.');
        return redirect()->route('admin.settings.edit');
    }

    /** The barangay's official GCash QR code and account details shown to consumers. */
    public function updateGcash(Request $request, Settings $settings, GcashQr $qr)
    {
        $number = preg_replace('/[\s-]+/', '', clean($request->input('gcash_number')));
        if ($number !== '' && !preg_match('/^(09|\+639)\d{9}$/', $number)) {
            flash('danger', 'GCash number must be a mobile number like 09171234567.');
            return back();
        }
        $settings->set('gcash_account_name', clean($request->input('gcash_account_name')));
        $settings->set('gcash_number', $number);

        if ($request->hasFile('gcash_qr')) {
            $errors = [];
            $name = $qr->files()->store($request->file('gcash_qr'), '', 'GCash QR code image', true, $errors);
            if ($errors) {
                flash('danger', $errors[0]);
                return back();
            }
            $old = $qr->fileName();
            $settings->set('gcash_qr_file', $name);
            $qr->files()->delete($old);
        }

        log_activity($request->user()->user_id, 'settings_update', 'Updated the GCash QR payment settings');
        flash('success', 'GCash payment settings saved.');
        return redirect()->route('admin.settings.edit');
    }
}
