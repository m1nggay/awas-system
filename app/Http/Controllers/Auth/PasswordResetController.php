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
        $identifier = clean($request->input('identifier'));
        if ($identifier === '') {
            return back();
        }

        $user = User::where('status', 'active')
            ->where(fn ($q) => $q->where('username', $identifier)->orWhere('email', $identifier))
            ->first();

        if ($user && !empty($user->email)) {
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
        }

        // Otherwise the same outcome whether or not the account exists, so this
        // form can't be used to discover registered usernames or emails.
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
            User::whereKey($userId)->update(['password_hash' => Hash::make($newPassword)]);
            $otp->invalidate($userId);
            log_activity($userId, 'password_reset', 'Password reset via emailed OTP');
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
