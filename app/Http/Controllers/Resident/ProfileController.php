<?php

namespace App\Http\Controllers\Resident;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Consumer profile: account details and contact info. Changing the password
 * uses the emailed-code flow in AccountController (same for every role).
 */
class ProfileController extends ResidentController
{
    public function show(Request $request)
    {
        return view('resident.profile', [
            'consumer' => $this->consumer($request),
            'user'     => $request->user(),
        ]);
    }

    public function updateContact(Request $request)
    {
        $email = clean($request->input('email')) ?: null;
        $contact = clean($request->input('contact_number')) ?: null;

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['Please enter a valid email address.']);
        }

        $request->user()->update(['email' => $email, 'contact_number' => $contact]);
        if ($consumer = $this->consumer($request)) {
            DB::table('consumers')->where('consumer_id', $consumer->consumer_id)
                ->update(['email' => $email, 'contact_number' => $contact]);
        }
        log_activity($request->user()->user_id, 'profile_update', 'Updated contact information (email / contact number)');
        flash('success', 'Contact information updated.');
        return redirect()->route('resident.profile');
    }
}
