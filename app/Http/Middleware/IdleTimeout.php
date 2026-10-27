<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs users out after config('agas.idle_timeout_minutes') of inactivity.
 * Automatic background checks (sent with an X-AWAS-Background header: the
 * admin's live updates, the session-timeout timer) are not activity — they
 * are timed out like anything else but never keep a forgotten session alive.
 */
class IdleTimeout
{
    public const BACKGROUND_HEADER = 'X-AWAS-Background';

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $last = (int)$request->session()->get('last_activity', time());
            if (time() - $last > config('agas.idle_timeout_minutes') * 60) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['error' => 'Your session has expired. Please refresh the page and log in again.'], 401)
                    : redirect()->route('login', ['timeout' => 1]);
            }
            if (!$request->headers->has(self::BACKGROUND_HEADER)) {
                $request->session()->put('last_activity', time());
            }
        }

        return $next($request);
    }

    /** Seconds left before this session times out. */
    public static function secondsLeft(Request $request): int
    {
        $last = (int)$request->session()->get('last_activity', time());
        return max(0, config('agas.idle_timeout_minutes') * 60 - (time() - $last));
    }
}
