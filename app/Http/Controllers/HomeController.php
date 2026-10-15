<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request, BillingService $billing)
    {
        if ($user = $request->user()) {
            return redirect()->route($user->dashboardRoute());
        }

        // The landing page shows the current water rate and billing rules from System Settings.
        $example = 15;
        return view('home', [
            'tariff'      => $billing->tariff(),
            'example'     => $example,
            'exampleBill' => $billing->computeBillAmount($example, false, date('Y-m')),
            'latePct'     => (float)setting('late_penalty_percent', 0),
            'dueDay'      => (int)setting('due_day_of_month', 19),
            'discMonths'  => max(1, (int)setting('disconnection_months', 3)),
            'phone'       => setting('contact_number'),
            'email'       => setting('contact_email'),
            'barangay'    => setting('barangay_name', 'Barangay Adlay'),
        ]);
    }
}
