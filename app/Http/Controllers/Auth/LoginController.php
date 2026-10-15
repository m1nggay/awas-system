<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.login', [
            'timeoutMsg'    => $request->has('timeout') ? 'Your session expired due to inactivity. Please log in again.' : '',
            'registeredMsg' => $request->has('registered') ? 'Account created successfully! You may now log in.' : '',
        ]);
    }

    public function login(Request $request)
    {
        $username = clean($request->input('username'));
        $password = (string)$request->input('password', '');

        if ($username === '' || $password === '') {
            return back()->withInput($request->only('username'))->with('error', 'Please enter both username and password.');
        }

        $user = User::where('username', $username)->first();

        // Membership applicants and the residents they become sign in with
        // their email address. Only tried when no username matched; if several
        // accounts share an email, the one whose password matches wins (and a
        // wrong password counts against the account only when there is one).
        if (!$user && str_contains($username, '@')) {
            $candidates = User::where('email', $username)->whereIn('role', ['applicant', 'resident'])->get();
            $user = $candidates->first(fn (User $candidate) => Hash::check($password, $candidate->password_hash))
                ?? ($candidates->count() === 1 ? $candidates->first() : null);
        }

        if (!$user) {
            log_activity(null, 'login_failed', "Unknown username attempt: $username");
            return $this->failed($request, 'These credentials do not match our records.');
        }

        // Locked after too many wrong passwords: Forgot Password or the administrator unlocks it.
        if ($user->isLocked()) {
            log_activity($user->user_id, 'login_blocked', 'Login attempt on a locked account');
            return $this->failed($request, self::lockedMessage());
        }

        if (!Hash::check($password, $user->password_hash)) {
            $attempts = (int)$user->failed_login_attempts + 1;
            $locked = $attempts >= User::MAX_LOGIN_ATTEMPTS;
            $user->forceFill(['failed_login_attempts' => $attempts, 'locked_at' => $locked ? now() : null])->save();

            if ($locked) {
                log_activity($user->user_id, 'account_locked', 'Account locked after ' . User::MAX_LOGIN_ATTEMPTS . ' failed login attempts');
                return $this->failed($request, self::lockedMessage());
            }
            log_activity($user->user_id, 'login_failed', "Incorrect password (attempt $attempts of " . User::MAX_LOGIN_ATTEMPTS . ')');
            $left = User::MAX_LOGIN_ATTEMPTS - $attempts;
            return $this->failed($request, "Incorrect password. $left " . ($left === 1 ? 'attempt' : 'attempts') . ' left before your account is locked.');
        }
        $user->clearLockout();

        // Checked only AFTER the password verifies, so a wrong guess never
        // reveals whether an account is awaiting email verification.
        if ($user->status === 'pending') {
            log_activity($user->user_id, 'login_blocked', 'Unverified (pending) account login attempt');
            $request->session()->put('email_verify_user_id', $user->user_id);
            $request->session()->put('email_verify_started_at', time());
            return redirect()->route('verify-email');
        }
        if ($user->status !== 'active') {
            log_activity($user->user_id, 'login_blocked', 'Inactive account login attempt');
            return $this->failed($request);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('last_activity', time());

        $user->forceFill(['last_login' => now()])->save();
        log_activity($user->user_id, 'login_success', 'User logged in');

        flash('success', 'Welcome, ' . $user->welcomeName());
        return redirect()->route($user->dashboardRoute());
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            log_activity($user->user_id, 'logout', 'User logged out');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Every role signs out to the landing page.
        return redirect()->route('home');
    }

    public static function lockedMessage(): string
    {
        return 'Your account is locked due to ' . User::MAX_LOGIN_ATTEMPTS . " failed login attempts. Click 'Forgot Password' to reset it, or contact the AWAS administrator.";
    }

    private function failed(Request $request, string $message = 'Invalid username or password.')
    {
        return back()->withInput($request->only('username'))->with('error', $message);
    }
}
