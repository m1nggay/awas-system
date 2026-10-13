<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\AccountController;
use App\Services\CodeMailer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Consumer profile: account details and contact info. Changing the password
 * uses the emailed-code flow in AccountController (same for every role).
 * Changing the email is confirmed the same way: a 6-digit code is sent to the
 * CURRENT email, and the new contact details are saved only after it is entered.
 */
class ProfileController extends ResidentController
{
    /** Session key holding a contact change that waits for the email code. */
    private const PENDING = 'pending_contact_change';

    public function show(Request $request)
    {
        $user = $request->user();
        $pending = $request->session()->get(self::PENDING);

        return view('resident.profile', [
            'consumer' => $this->consumer($request),
            'user'     => $user,
            'pending'  => $pending,
            'resendIn' => $pending ? OtpService::emailChange()->secondsUntilResend($user->user_id) : 0,
            // Change password happens right in the Security card (same steps as the Change Password page).
            'pwStep'     => AccountController::passwordStep($request),
            'pwResendIn' => OtpService::passwordReset()->secondsUntilResend($user->user_id),
            'pwTtl'      => OtpService::passwordReset()->ttlMinutes,
        ]);
    }

    public function updateContact(Request $request, CodeMailer $codes)
    {
        $user = $request->user();
        $email = mb_strtolower(clean($request->input('email')));
        // Typed after a "+63" prefix, with or without spaces / a leading 0; stored as 09XXXXXXXXX.
        $digits = preg_replace('/\D+/', '', (string)$request->input('contact_number'));
        $digits = preg_replace('/^(63|0)(?=9\d{9}$)/', '', $digits);

        $error = match (true) {
            $email === '' && $user->email => 'Please enter your email address — it is where your password codes are sent.',
            $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
            $email !== '' && DB::table('users')->where('email', $email)->where('user_id', '!=', $user->user_id)->exists()
                => 'That email address is already used by another account.',
            $digits !== '' && !preg_match('/^9\d{9}$/', $digits) => 'Please enter a valid mobile number, e.g. 985 588 5588.',
            default => null,
        };
        if ($error) {
            return back()->withErrors([$error])->withInput();
        }

        $contact = $digits !== '' ? '0' . $digits : null;
        $email = $email ?: null;

        // A new email is confirmed with a code sent to the current one (if there is one).
        if ($email !== null && $user->email && $email !== mb_strtolower($user->email)) {
            $result = $codes->sendEmailChangeCode($user->user_id, $user->email, $user->full_name, $email);
            [$type, $message] = OtpService::resultMessage($result, OtpService::emailChange()->secondsUntilResend($user->user_id));
            if (!in_array($result, ['sent', 'cooldown'], true)) {
                return back()->withErrors([$message])->withInput();
            }
            $request->session()->put(self::PENDING, ['email' => $email, 'contact_number' => $contact]);
            if ($result === 'sent') {
                log_activity($user->user_id, 'email_change_requested', "Requested to change email to $email (code sent to the current email)");
            }
            flash($type === 'success' ? 'success' : 'warning', $type === 'success'
                ? 'We sent a 6-digit code to your current email. Enter it below to confirm the change.' : $message);
            return redirect()->route('resident.profile');
        }

        $this->saveContact($request, $email, $contact);
        log_activity($user->user_id, 'profile_update', 'Updated contact information (email / contact number)');
        flash('success', 'Contact information updated.');
        return redirect()->route('resident.profile');
    }

    /** The code from the current email: saves the waiting change. */
    public function confirmEmail(Request $request)
    {
        $user = $request->user();
        $pending = $request->session()->get(self::PENDING);
        if (!$pending) {
            return redirect()->route('resident.profile');
        }
        $code = preg_replace('/\D+/', '', (string)$request->input('otp'));
        if (!preg_match('/^\d{6}$/', $code)) {
            return back()->withErrors(['Please enter the 6-digit code from your email.']);
        }

        $result = OtpService::emailChange()->verify($user->user_id, $code);
        if ($result !== 'ok') {
            if (in_array($result, ['locked', 'expired'], true)) {
                $request->session()->forget(self::PENDING);
            }
            return back()->withErrors([match ($result) {
                'expired' => 'That code has expired. Please save your new email again to get a new code.',
                'locked'  => 'Too many incorrect attempts. Please save your new email again to get a new code.',
                default   => 'Incorrect code. Please try again.',
            }]);
        }

        $old = $user->email;
        $this->saveContact($request, $pending['email'], $pending['contact_number']);
        $request->session()->forget(self::PENDING);
        log_activity($user->user_id, 'email_change', "Changed email from $old to {$pending['email']} (verified by a code sent to the old email)");
        flash('success', 'Your email was changed.');
        return redirect()->route('resident.profile');
    }

    public function resendEmailCode(Request $request, CodeMailer $codes)
    {
        $user = $request->user();
        $pending = $request->session()->get(self::PENDING);
        if (!$pending || !$user->email) {
            return redirect()->route('resident.profile');
        }
        $result = $codes->sendEmailChangeCode($user->user_id, $user->email, $user->full_name, $pending['email']);
        [$type, $message] = OtpService::resultMessage($result, OtpService::emailChange()->secondsUntilResend($user->user_id));
        flash($type, $message);
        return redirect()->route('resident.profile');
    }

    public function cancelEmailChange(Request $request)
    {
        OtpService::emailChange()->invalidate($request->user()->user_id);
        $request->session()->forget(self::PENDING);
        flash('success', 'Email change cancelled. Your email stays the same.');
        return redirect()->route('resident.profile');
    }

    private function saveContact(Request $request, ?string $email, ?string $contact): void
    {
        DB::table('users')->where('user_id', $request->user()->user_id)->update(['email' => $email, 'contact_number' => $contact]);
        if ($consumer = $this->consumer($request)) {
            DB::table('consumers')->where('consumer_id', $consumer->consumer_id)->update(['email' => $email, 'contact_number' => $contact]);
        }
    }
}
