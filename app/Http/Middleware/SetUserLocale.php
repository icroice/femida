<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale: for guests — from the session (after /locale/{ru|en}), for users — from the profile.
 * Default locale is EN (env APP_LOCALE).
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale', config('app.locale'));

        App::setLocale(in_array($locale, ['ru', 'en'], true) ? $locale : 'en');

        return $next($request);
    }
}
