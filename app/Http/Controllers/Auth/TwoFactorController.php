<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CodeMailer;
use App\Services\OtpService;
use Illuminate\Http\Request;

/**
 * Two-factor login for administrator accounts. After the password (or
 * Google / Facebook) checks out, the administrator is NOT signed in yet:
 * a 6-digit code is emailed to them, and only the right code finishes the
 * sign-in. Too many wrong codes, or taking too long, means starting over
 * from the password.
 */
class TwoFactorController extends Controller
{
    /** The half-finished sign-in expires on its own. */
    private const SESSION_TTL_SECONDS = 15 * 60;

    /** Step two begins: remember who passed step one, email the code, show the code page. */
    public static function challenge(Request $request, User $user, string $logMessage)
    {
        if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            log_activity($user->user_id, 'login_blocked', 'Administrator sign-in stopped: no email address for the login code');
            return redirect()->route('login')->withInput(['username' => $user->username])
                ->with('error', 'This administrator account has no email address to send the login code to. Ask another administrator to add one in Manage Users.');
        }

        $request->session()->put('two_factor', ['user_id' => $user->user_id, 'started_at' => time(), 'log' => $logMessage]);
        $result = app(CodeMailer::class)->sendLoginCode($user->user_id, $user->email, $user->full_name);
        log_activity($user->user_id, 'login_2fa_sent', $result === 'sent'
            ? 'Password accepted — login code emailed (two-factor)' : "Password accepted — login code not sent ($result)");

        return redirect()->route('two-factor')->with('code_result', $result);
    }

    public function show(Request $request)
    {
        $user = $this->pendingUser($request);
        if (!$user instanceof User) {
            return $user;
        }
        $otp = OtpService::login();
        $result = session('code_result');

        return view('auth.two-factor', [
            'email'      => self::maskEmail($user->email),
            'ttl'        => $otp->ttlMinutes,
            'codeNotice' => $result ? OtpService::resultMessage($result, $otp->secondsUntilResend($user->user_id)) : null,
            'resendIn'   => $otp->secondsUntilResend($user->user_id),
        ]);
    }

    public function resend(Request $request, CodeMailer $codes)
    {
        $user = $this->pendingUser($request);
        if (!$user instanceof User) {
            return $user;
        }
        return redirect()->route('two-factor')->with('code_result', $codes->sendLoginCode($user->user_id, $user->email, $user->full_name));
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);
        if (!$user instanceof User) {
            return $user;
        }
        $code = preg_replace('/\D/', '', (string)$request->input('otp'));
        if (strlen($code) !== 6) {
            return back()->with('error', 'Please enter the 6-digit code from the email.');
        }

        $otp = OtpService::login();
        $result = $otp->verify($user->user_id, $code);
        if ($result === 'ok') {
            $otp->invalidate($user->user_id);
            $log = $request->session()->pull('two_factor')['log'] ?? 'User logged in';
            return LoginController::finishLogin($request, $user, "$log (two-factor code verified)");
        }

        log_activity($user->user_id, 'login_2fa_failed', match ($result) {
            'locked'  => 'Too many wrong login codes — sign-in cancelled',
            'expired' => 'Expired login code entered',
            default   => 'Wrong login code entered',
        });
        if ($result === 'locked') {
            // Start over from the password, so codes can't be guessed endlessly.
            $request->session()->forget('two_factor');
            $otp->invalidate($user->user_id);
            return redirect()->route('login')->with('error', 'Too many incorrect codes. For your security, please sign in again.');
        }
        return back()->with('error', $result === 'expired'
            ? 'That code has expired. Click Resend Code to get a new one.'
            : 'Incorrect code. Please check the latest email and try again.');
    }

    /** Back to the login page: cancels the half-finished sign-in. */
    public function cancel(Request $request)
    {
        $request->session()->forget('two_factor');
        return redirect()->route('login');
    }

    /** The administrator who passed the password step in this browser, or a redirect to start over. */
    private function pendingUser(Request $request): User|\Illuminate\Http\RedirectResponse
    {
        $pending = $request->session()->get('two_factor');
        $user = $pending ? User::find($pending['user_id']) : null;
        if (!$user || time() - $pending['started_at'] > self::SESSION_TTL_SECONDS || $user->status !== 'active' || $user->isLocked()) {
            $request->session()->forget('two_factor');
            return redirect()->route('login')->with('error', $pending
                ? 'Your sign-in took too long. Please enter your password again.'
                : 'Please sign in first.');
        }
        return $user;
    }

    /** "ad*****@gmail.com" — enough to recognise, not enough to harvest. */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($name, 0, 2) . '*****@' . $domain;
    }
}
