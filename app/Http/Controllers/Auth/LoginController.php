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
        // accounts share an email, the one whose password matches wins.
        if (!$user && str_contains($username, '@')) {
            $user = User::where('email', $username)->whereIn('role', ['applicant', 'resident'])->get()
                ->first(fn (User $candidate) => Hash::check($password, $candidate->password_hash));
        }

        if (!$user) {
            log_activity(null, 'login_failed', "Unknown username attempt: $username");
            return $this->failed($request);
        }
        if (!Hash::check($password, $user->password_hash)) {
            log_activity($user->user_id, 'login_failed', 'Incorrect password');
            return $this->failed($request);
        }

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

        return redirect()->route('login');
    }

    private function failed(Request $request)
    {
        return back()->withInput($request->only('username'))->with('error', 'Invalid username or password.');
    }
}
