<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

/**
 * "Continue with Google / Facebook" (Laravel Socialite).
 *
 * Signs in an EXISTING AWAS account — new residents still apply through
 * "Create an account" (membership needs an ID and the administrator's approval).
 * The account is found by the linked provider ID, or else by the same email
 * address (then linked for next time). From Profile, a signed-in user can also
 * connect / disconnect an account whose email differs.
 */
class SocialLoginController extends Controller
{
    public const PROVIDERS = ['google' => 'Google', 'facebook' => 'Facebook'];

    /** Providers with a client ID and secret set, e.g. ['google' => 'Google']. */
    public static function enabled(): array
    {
        return array_filter(self::PROVIDERS, fn ($name, $key) => config("services.$key.client_id") && config("services.$key.client_secret"),
            ARRAY_FILTER_USE_BOTH);
    }

    /** Off to Google / Facebook. ?link=1 from Profile connects it to the signed-in account instead. */
    public function redirect(Request $request, string $provider)
    {
        abort_unless(isset(self::enabled()[$provider]), 404);
        if ($request->user() && $request->boolean('link')) {
            $request->session()->put('social_link', $provider);
        } else {
            $request->session()->forget('social_link');
        }
        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(isset(self::enabled()[$provider]), 404);
        $name = self::PROVIDERS[$provider];
        $linking = $request->user() && $request->session()->pull('social_link') === $provider;

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            report($e);
            flash('danger', "$name sign-in was cancelled or did not finish. Please try again.");
            return $linking ? redirect($this->profileUrl($request->user())) : redirect()->route('login');
        }

        return $linking ? $this->link($request, $provider, (string)$social->getId())
            : $this->signIn($request, $provider, (string)$social->getId(), $this->verifiedEmail($provider, $social));
    }

    /** Profile → "Disconnect". */
    public function unlink(Request $request, string $provider)
    {
        abort_unless(isset(self::PROVIDERS[$provider]), 404);
        $user = $request->user();
        $user->forceFill([$provider . '_id' => null])->save();
        log_activity($user->user_id, 'social_unlink', 'Disconnected ' . self::PROVIDERS[$provider] . ' sign-in');
        flash('success', self::PROVIDERS[$provider] . ' is disconnected. Sign in with your username and password.');
        return redirect($this->profileUrl($user));
    }

    private function signIn(Request $request, string $provider, string $socialId, ?string $email)
    {
        $name = self::PROVIDERS[$provider];
        $column = $provider . '_id';

        $user = User::where($column, $socialId)->first();
        if (!$user && $email) {
            // Same email: the active account first (a resident over the applicant record it came from).
            $user = User::whereRaw('LOWER(email) = ?', [$email])->get()
                ->sortBy(fn (User $u) => [$u->status === 'active' ? 0 : 1, array_search($u->role, ['admin', 'staff', 'resident', 'applicant'], true)])
                ->first();
            if ($user && !User::where($column, $socialId)->exists()) {
                $user->forceFill([$column => $socialId])->save();
                log_activity($user->user_id, 'social_link', "Connected $name sign-in (same email)");
            }
        }

        if (!$user) {
            log_activity(null, 'login_failed', "$name sign-in with no matching AWAS account" . ($email ? " ($email)" : ''));
            flash('danger', "No AWAS account uses this $name account" . ($email ? " ($email)" : '') . '. Sign in with your username and password, then connect '
                . "$name in your Profile — or apply for membership with Create an account.");
            return redirect()->route('login');
        }
        if ($user->status === 'pending') {
            $request->session()->put('email_verify_user_id', $user->user_id);
            $request->session()->put('email_verify_started_at', time());
            return redirect()->route('verify-email');
        }
        if ($user->status !== 'active') {
            log_activity($user->user_id, 'login_blocked', "Inactive account $name sign-in attempt");
            flash('danger', 'This account is not active. Please contact the AWAS administrator.');
            return redirect()->route('login');
        }

        // Signing in through the account's own Google / Facebook proves it is theirs, like Forgot Password.
        $user->clearLockout();
        return LoginController::startSession($request, $user, "User logged in with $name");
    }

    private function link(Request $request, string $provider, string $socialId)
    {
        $name = self::PROVIDERS[$provider];
        $user = $request->user();
        $owner = User::where($provider . '_id', $socialId)->first();
        if ($owner && $owner->user_id !== $user->user_id) {
            flash('danger', "That $name account is already connected to another AWAS account.");
        } else {
            $user->forceFill([$provider . '_id' => $socialId])->save();
            log_activity($user->user_id, 'social_link', "Connected $name sign-in");
            flash('success', "$name is connected. You can now sign in with \"Continue with $name\".");
        }
        return redirect($this->profileUrl($user));
    }

    /** The email, only when the provider says it is verified (Google tells us; Facebook only shares verified ones). */
    private function verifiedEmail(string $provider, $social): ?string
    {
        $email = mb_strtolower(trim((string)$social->getEmail()));
        if ($email === '' || ($provider === 'google' && empty($social->user['email_verified']))) {
            return null;
        }
        return $email;
    }

    private function profileUrl(User $user): string
    {
        return ($user->role === 'resident' ? route('resident.profile') : route('admin.profile')) . '#security';
    }
}
