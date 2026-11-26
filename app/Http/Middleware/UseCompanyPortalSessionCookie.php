<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the B2B Company Portal (/b2b/company/*) its own session cookie.
 *
 * Royal Admin (/admin) and the Company Portal are served from the same host and
 * both use the session-based "web" guard. With a single session cookie, signing in
 * to one portal replaced the other portal's session in the same browser, so a Royal
 * admin who also opened the Company Portal got 403 on every admin page afterwards.
 *
 * The portal cookie has a different name and is scoped to the /b2b/company path, so
 * it is never sent to /admin and the admin cookie is never read by the portal.
 * Must run before StartSession (prepended to the web middleware group).
 */
final class UseCompanyPortalSessionCookie
{
    public const PATH = '/b2b/company';
    public const SUFFIX = '_company_portal';

    public function handle(Request $request, Closure $next): Response
    {
        // Remember the configured defaults once, so every request (including several
        // requests in one long-running process) starts from the same base values.
        if (config('session.portal_base_cookie') === null) {
            config([
                'session.portal_base_cookie' => config('session.cookie'),
                'session.portal_base_path' => config('session.path', '/'),
            ]);
        }

        $isPortal = $request->is('b2b/company') || $request->is('b2b/company/*');
        $name = config('session.portal_base_cookie') . ($isPortal ? self::SUFFIX : '');

        config([
            'session.cookie' => $name,
            'session.path' => $isPortal ? self::PATH : config('session.portal_base_path'),
        ]);
        // The session store may already be resolved; keep its cookie name in sync.
        app('session')->driver()->setName($name);

        return $next($request);
    }
}
