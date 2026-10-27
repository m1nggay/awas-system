<?php

namespace App\Http\Controllers;

use App\Http\Middleware\IdleTimeout;
use Illuminate\Http\Request;

/**
 * For the inactivity timer on every signed-in page (session-timeout.js):
 * how long is left (a background check, not activity), and "Stay signed in".
 * A session that has already timed out gets a 401 from IdleTimeout instead.
 */
class SessionController extends Controller
{
    public function status(Request $request)
    {
        return response()->json(['remaining' => IdleTimeout::secondsLeft($request)]);
    }

    /** "Stay signed in": an ordinary request, so IdleTimeout has just restarted the clock. */
    public function keepAlive(Request $request)
    {
        return response()->json(['remaining' => IdleTimeout::secondsLeft($request)]);
    }
}
