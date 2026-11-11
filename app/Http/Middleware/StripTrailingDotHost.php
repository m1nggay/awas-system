<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "awas-system.online." (with a final dot, e.g. typed or autocompleted) is a different
 * host to the browser: its cookies, sessions and the Google sign-in return address would
 * not match. Send such visits to the same page on the normal address.
 */
class StripTrailingDotHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        if (str_ends_with($host, '.')) {
            $port = $request->getPort();
            $url = $request->getScheme() . '://' . rtrim($host, '.')
                . (in_array($port, [80, 443], true) ? '' : ':' . $port) . $request->getRequestUri();
            return redirect()->away($url, 301);
        }
        return $next($request);
    }
}
