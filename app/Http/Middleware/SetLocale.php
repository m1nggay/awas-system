<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * English or Tagalog for every page. The EN | TL switch on the public pages saves
 * the choice in a cookie; a signed-in user's choice is also kept on their account
 * (users.locale), so notifications and emails can be written in it and a new phone
 * starts in the right language.
 */
class SetLocale
{
    public const LOCALES = ['en', 'tl'];

    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $request->cookie('locale');
        $user = $request->user();

        if (in_array($cookie, self::LOCALES, true)) {
            $locale = $cookie;
            if ($user && $user->locale !== $locale) {
                $user->forceFill(['locale' => $locale])->save();
            }
        } else {
            $locale = in_array($user?->locale, self::LOCALES, true) ? $user->locale : 'en';
        }

        app()->setLocale($locale);
        return $next($request);
    }
}
