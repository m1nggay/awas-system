<?php

namespace App\Http\Controllers;

use App\Services\BillingService;

/** Public Privacy Policy and Terms of Service (linked from sign-in pages and Google's consent screen). */
class LegalController extends Controller
{
    /** Contact address printed in both documents. */
    public const CONTACT_EMAIL = 'adlaywateraugmentationsystem@gmail.com';

    public function privacy()
    {
        return view('legal.privacy', $this->details());
    }

    public function terms(BillingService $billing)
    {
        // Rates, due day, penalty, discount and disconnection come from System Settings, so the terms stay current.
        return view('legal.terms', $this->details() + [
            'tariff'     => $billing->tariff(),
            'dueDay'     => (int)setting('due_day_of_month', 19),
            'latePct'    => (float)setting('late_penalty_percent', 0),
            'discMonths' => max(1, (int)setting('disconnection_months', 3)),
        ]);
    }

    private function details(): array
    {
        return [
            'effective' => localizeMonths('November 11, 2026'),
            'barangay'  => setting('barangay_name', 'Barangay Adlay'),
            'email'     => self::CONTACT_EMAIL,
            'photoDays' => config('agas.application_photo_days'),
        ];
    }
}
