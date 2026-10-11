<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Profile for administrators and meter readers: picture, account details,
 * contact info, and a link to change the password (emailed-code flow).
 */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $ids = $user->assignedPurokIds();

        return view('admin.profile', [
            'user'   => $user,
            'puroks' => $ids === null ? null : DB::table('puroks')->whereIn('purok_id', $ids ?: [0])->orderBy('purok_name')->pluck('purok_name'),
        ]);
    }

    public function updateContact(Request $request)
    {
        $email = clean($request->input('email')) ?: null;
        $contact = clean($request->input('contact_number')) ?: null;

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['Please enter a valid email address.']);
        }
        if ($email !== null && DB::table('users')->where('email', $email)->where('user_id', '!=', $request->user()->user_id)->exists()) {
            return back()->withErrors(['That email address is already used by another account.']);
        }

        DB::table('users')->where('user_id', $request->user()->user_id)->update(['email' => $email, 'contact_number' => $contact]);
        log_activity($request->user()->user_id, 'profile_update', 'Updated contact information (email / contact number)');
        flash('success', 'Contact information updated.');
        return redirect()->route('admin.profile');
    }
}
