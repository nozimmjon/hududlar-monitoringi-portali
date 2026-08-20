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

        $response->headers->set(
            'Content-Security-Policy',
            'frame-ancestors '.implode(' ', self::ALLOWED_ANCESTORS),
        );

        return $response;
    }
}
