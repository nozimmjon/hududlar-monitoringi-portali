<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the portal to be embedded in an <iframe> only by the origins
 * listed here (plus our own pages). Browsers that honour CSP ignore
 * X-Frame-Options when frame-ancestors is present, so this single
 * header is the whole policy.
 *
 * The login page and the whole /admin panel opt out of the allow-list above and get
 * 'none' instead — a clickjacked login form or admin action is worse than a clickjacked
 * public dashboard, so even the partner origin does not get to frame those pages.
 */
class SetFrameAncestors
{
    /** @var list<string> */
    private const ALLOWED_ANCESTORS = [
        "'self'",
        'https://du.egov.uz',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $policy = $request->is('login', 'logout', 'admin', 'admin/*')
            ? "frame-ancestors 'none'"
            : 'frame-ancestors '.implode(' ', self::ALLOWED_ANCESTORS);

        $response->headers->set('Content-Security-Policy', $policy);

        return $response;
    }
}
