<?php

namespace App\Http\Controllers;

/** Public Privacy Policy and Terms of Service (linked from sign-in pages and Google's consent screen). */
class LegalController extends Controller
{
    public function privacy()
    {
        return view('legal.privacy', $this->details());
    }

    public function terms()
    {
        return view('legal.terms', $this->details());
    }

    private function details(): array
    {
        return [
            'effective' => 'October 15, 2026',
            'barangay'  => setting('barangay_name', 'Barangay Adlay'),
            'email'     => setting('contact_email'),
            'phone'     => setting('contact_number'),
        ];
    }
}
