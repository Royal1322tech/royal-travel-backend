<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['ar', 'en'];
        $requested = $request->header('X-Locale');
        if (! $requested) {
            $requested = $request->query('lang');
        }
        if (! $requested) {
            $requested = $request->getPreferredLanguage($supported);
        }
        $locale = in_array($requested, $supported, true) ? $requested : 'en';
        app()->setLocale($locale);
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        $response->headers->set('Vary', 'Accept-Language, X-Locale');
        return $response;
    }
}
