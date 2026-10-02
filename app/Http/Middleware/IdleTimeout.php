<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs users out after config('agas.idle_timeout_minutes') of inactivity. */
class IdleTimeout
{
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
            $request->session()->put('last_activity', time());
        }

        return $next($request);
    }
}
