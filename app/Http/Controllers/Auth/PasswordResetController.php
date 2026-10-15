<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CodeMailer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** "Forgot Password": emailed 6-digit code, then a new password. */
class PasswordResetController extends Controller
{
    /** The reset session expires on its own so an old tab can't be reused forever. */
    private const SESSION_TTL_SECONDS = 20 * 60;

    public function showRequest()
    {
        return view('auth.forgot-password', ['ttl' => OtpService::passwordReset()->ttlMinutes]);
    }

    public function sendCode(Request $request, CodeMailer $codes)
    {
        // Only a real Gmail address that belongs to an AWAS account.
        $request->merge(['email' => mb_strtolower(clean($request->input('email')))]);
        $request->validate([
            'email' => ['required', 'email:rfc,dns', 'regex:/^[a-z0-9](\.?[a-z0-9]){5,29}@gmail\.com$/i', 'exists:users,email'],
        ], [
            'email.required' => 'Please enter your Gmail address.',
            'email.email'    => 'Please enter a valid Gmail address (e.g. juan.delacruz@gmail.com).',
            'email.regex'    => 'Please enter a valid Gmail address (e.g. juan.delacruz@gmail.com).',
            'email.exists'   => 'No AWAS account is registered with this Gmail address.',
        ]);

        $user = User::where('status', 'active')->where('email', $request->input('email'))->first();

        // The email exists, but not on an active account (e.g. deactivated).
        if (!$user) {
            return back()->withInput()->withErrors(['email' => 'This account is not active. Please contact the AWAS administrator.']);
        }

        $result = $codes->sendPasswordCode($user->user_id, $user->email, $user->full_name,
            'Your AWAS password reset code', 'Your AWAS password reset code is:');

        // Never claim a code was sent when the email actually failed.
        if (in_array($result, ['failed', 'limit'], true)) {
            return back()->withInput()->with('error', OtpService::resultMessage($result)[1]);
        }
        if ($result === 'sent') {
            log_activity($user->user_id, 'password_reset_requested', 'Password reset code emailed');
        }
        $request->session()->put('pwd_reset_user_id', $user->user_id);
        $request->session()->put('pwd_reset_started_at', time());

        return redirect()->route('password.forgot')->with('sent', true);
    }

    public function showReset(Request $request)
    {
        if (!$this->resetUserId($request)) {
            return redirect()->route('password.forgot');
        }
        return view('auth.reset-password');
    }

    public function reset(Request $request)
    {
        $userId = $this->resetUserId($request);
        if (!$userId) {
            return redirect()->route('password.forgot');
        }

        $code = clean($request->input('otp'));
        $newPassword = (string)$request->input('new_password', '');
        $confirm = (string)$request->input('confirm_password', '');

        if ($code === '' || $newPassword === '') {
            return back()->with('error', 'Please enter the code and a new password.');
        }
        if ($policy = passwordPolicyError($newPassword)) {
            return back()->with('error', $policy);
        }
        if ($newPassword !== $confirm) {
            return back()->with('error', 'Passwords do not match.');
        }

        $otp = OtpService::passwordReset();
        $result = $otp->verify($userId, $code);

        if ($result === 'ok') {
            // A new password also unlocks an account locked by failed logins.
            User::whereKey($userId)->update(['password_hash' => Hash::make($newPassword), 'failed_login_attempts' => 0, 'locked_at' => null]);
            $otp->invalidate($userId);
            log_activity($userId, 'password_reset', 'Password reset via emailed OTP (account unlocked)');
            $request->session()->forget(['pwd_reset_user_id', 'pwd_reset_started_at']);
            return view('auth.reset-password', ['success' => true]);
        }

        if ($result === 'locked') {
            $request->session()->forget(['pwd_reset_user_id', 'pwd_reset_started_at']);
            return view('auth.reset-password', ['lockedError' => 'Too many incorrect attempts. Please request a new code.']);
        }

        return back()->with('error', $result === 'expired'
            ? 'That code has expired or is no longer valid. Please request a new one.'
            : 'Incorrect code. Please try again.');
    }

    private function resetUserId(Request $request): ?int
    {
        $userId = $request->session()->get('pwd_reset_user_id');
        $startedAt = (int)$request->session()->get('pwd_reset_started_at', 0);

        if (!$userId || (time() - $startedAt) > self::SESSION_TTL_SECONDS) {
            $request->session()->forget(['pwd_reset_user_id', 'pwd_reset_started_at']);
            return null;
        }
        return (int)$userId;
    }
}
