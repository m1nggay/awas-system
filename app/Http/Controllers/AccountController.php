<?php

namespace App\Http\Controllers;

use App\Services\CodeMailer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * "My Account" — change password with email OTP, for every signed-in role:
 *   1. request a code (sent to the registered email)
 *   2. enter the code (hashed in the DB, expires, limited attempts)
 *   3. only then choose the new password (within 10 minutes)
 */
class AccountController extends Controller
{
    private const VERIFIED_TTL_SECONDS = 10 * 60;

    public function show(Request $request)
    {
        $user = $request->user();
        $otp = OtpService::passwordReset();

        return view('account.password', [
            'user'     => $user,
            'step'     => self::passwordStep($request),
            'resendIn' => $otp->secondsUntilResend($user->user_id),
            'ttl'      => $otp->ttlMinutes,
            'layout'   => $user->role === 'applicant' ? 'auth' : 'app',
        ]);
    }

    public function sendCode(Request $request, CodeMailer $codes)
    {
        $user = $request->user();
        if (empty($user->email)) {
            return back()->with('error', 'There is no email address on your account. Please ask the administrator to add one first.');
        }

        $result = $codes->sendPasswordCode($user->user_id, $user->email, $user->full_name,
            'Your AWAS change-password code', 'Use this code to confirm that you want to change your AWAS password:');
        [$type, $message] = OtpService::resultMessage($result, OtpService::passwordReset()->secondsUntilResend($user->user_id));

        if (in_array($result, ['sent', 'cooldown'], true)) {
            $request->session()->put('pwd_change_code_sent', true);
        }
        if ($result === 'sent') {
            log_activity($user->user_id, 'password_change_requested', 'Requested a change-password code by email');
        }
        return $this->stepPage($request)->with($type === 'success' ? 'success' : 'error', $message);
    }

    public function verifyCode(Request $request)
    {
        $user = $request->user();
        $code = clean($request->input('otp'));
        if (!preg_match('/^\d{6}$/', $code)) {
            return back()->with('error', 'Please enter the 6-digit code from your email.');
        }

        $result = OtpService::passwordReset()->verify($user->user_id, $code);
        if ($result !== 'ok') {
            if ($result === 'locked' || $result === 'expired') {
                $request->session()->forget('pwd_change_code_sent');
            }
            return back()->with('error', match ($result) {
                'expired' => 'That code has expired or is no longer valid. Please request a new one.',
                'locked'  => 'Too many incorrect attempts. Please request a new code.',
                default   => 'Incorrect code. Please try again.',
            });
        }

        $request->session()->put('pwd_change_verified_at', time());
        $request->session()->forget('pwd_change_code_sent');
        return $this->stepPage($request)->with('success', 'Code verified. You can now choose a new password.');
    }

    public function update(Request $request)
    {
        if (!$this->isVerified($request)) {
            return $this->stepPage($request)->with('error', 'Please verify the code sent to your email first.');
        }

        $user = $request->user();
        $new = (string)$request->input('new_password', '');
        $error = match (true) {
            ($policy = passwordPolicyError($new)) !== null => $policy,
            $new !== (string)$request->input('confirm_password', '') => 'New password and confirmation do not match.',
            Hash::check($new, $user->password_hash) => 'Please choose a password different from your current one.',
            default => null,
        };
        if ($error) {
            return back()->with('error', $error);
        }

        $user->update(['password_hash' => Hash::make($new)]);
        OtpService::passwordReset()->invalidate($user->user_id);
        $request->session()->forget(['pwd_change_verified_at', 'pwd_change_code_sent']);
        log_activity($user->user_id, 'password_change', 'Changed own password (verified by email code)');

        flash('success', 'Your password was changed.');
        return redirect()->route(match (true) {
            $user->role === 'resident' => 'resident.profile',
            $request->input('from') === 'profile' => 'admin.profile',
            default => $user->dashboardRoute(),
        });
    }

    /** Which step the signed-in user is on: 'request' (get a code), 'code' (enter it) or 'new' (new password). */
    public static function passwordStep(Request $request): string
    {
        return (new self())->isVerified($request) ? 'new' : ($request->session()->get('pwd_change_code_sent') ? 'code' : 'request');
    }

    private function isVerified(Request $request): bool
    {
        $at = (int)$request->session()->get('pwd_change_verified_at', 0);
        return $at > 0 && (time() - $at) <= self::VERIFIED_TTL_SECONDS;
    }

    /** Back to where the steps are shown: My Profile's Security card, or the Change Password page. */
    private function stepPage(Request $request)
    {
        if ($request->input('from') !== 'profile') {
            return redirect()->route('account.password');
        }
        return match ($request->user()->role) {
            'resident'       => redirect()->to(route('resident.profile') . '#security'),
            'admin', 'staff' => redirect()->to(route('admin.profile') . '#security'),
            default          => redirect()->route('account.password'),
        };
    }
}
