<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Profile for administrators and meter readers: picture, account details,
 * contact info, and changing the password right on the page (emailed-code flow).
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
            // Change password happens right in the Security card (same steps as the Change Password page).
            'pwStep'     => AccountController::passwordStep($request),
            'pwResendIn' => OtpService::passwordReset()->secondsUntilResend($user->user_id),
            'pwTtl'      => OtpService::passwordReset()->ttlMinutes,
        ]);
    }

    public function updateContact(Request $request)
    {
        $email = mb_strtolower(clean($request->input('email'))) ?: null;
        // Typed after a "+63" prefix, with or without spaces / a leading 0; stored as 09XXXXXXXXX.
        $digits = preg_replace('/\D+/', '', (string)$request->input('contact_number'));
        $digits = preg_replace('/^(63|0)(?=9\d{9}$)/', '', $digits);

        $error = match (true) {
            $email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
            $email !== null && DB::table('users')->where('email', $email)->where('user_id', '!=', $request->user()->user_id)->exists()
                => 'That email address is already used by another account.',
            $digits !== '' && !preg_match('/^9\d{9}$/', $digits) => 'Please enter a valid mobile number, e.g. 985 588 5588.',
            default => null,
        };
        if ($error) {
            return back()->withErrors([$error])->withInput();
        }

        DB::table('users')->where('user_id', $request->user()->user_id)
            ->update(['email' => $email, 'contact_number' => $digits !== '' ? '0' . $digits : null]);
        log_activity($request->user()->user_id, 'profile_update', 'Updated contact information (email / contact number)');
        flash('success', 'Contact information updated.');
        return redirect()->route('admin.profile');
    }
}
