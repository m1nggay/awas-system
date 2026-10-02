<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access control — route usage: ->middleware('role:admin,staff').
 * Replaces the original requireLogin([...roles]).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Please sign in.'], 401)
                : redirect()->route('login');
        }

        if ($roles && !in_array($user->role, $roles, true)) {
            return $request->expectsJson()
                ? response()->json(['error' => 'You do not have permission to do that.'], 403)
                : response()->view('errors.access-denied', [], 403);
        }

        return $next($request);
    }
}
