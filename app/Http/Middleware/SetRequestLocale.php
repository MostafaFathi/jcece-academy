<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestedLocale = $request->header('X-Locale');
        $preferredLocale = $request->user()?->preferred_locale;
        $locale = in_array($requestedLocale, ['ar', 'en'], true)
            ? $requestedLocale
            : (in_array($preferredLocale, ['ar', 'en'], true) ? $preferredLocale : config('app.locale', 'en'));
        app()->setLocale($locale);

        return $next($request);
    }
}
