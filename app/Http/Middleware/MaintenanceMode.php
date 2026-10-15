<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode (System Settings → Maintenance). While it is on, only
 * administrators can use AWAS; everyone else sees the maintenance page and
 * signed-in consumers / meter readers are signed out. The login and password
 * reset pages stay open so an administrator can still get in. PayMongo
 * webhooks use the "api" group, so payments keep being confirmed.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(Settings::class);
        if ($settings->get('maintenance_mode', '0') !== '1') {
            return $next($request);
        }

        $user = $request->user();
        if ($user && $user->isAdmin()) {
            return $next($request);
        }
        if (!$user && $request->routeIs('login', 'login.attempt', 'logout', 'password.*', 'privacy', 'terms', 'social.*')) {
            return $next($request);
        }
        if ($user) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $message = (string)$settings->get('maintenance_message', '');
        $until = (string)$settings->get('maintenance_until', '');
        if ($request->expectsJson()) {
            return response()->json(['error' => 'AWAS is under maintenance. Please try again later.'], 503);
        }
        return response()->view('maintenance', [
            'message' => $message !== '' ? $message : 'AWAS is being updated to serve you better. Please check back soon.',
            'until'   => $until !== '' && strtotime($until) ? \Carbon\Carbon::parse($until) : null,
        ], 503);
    }
}
