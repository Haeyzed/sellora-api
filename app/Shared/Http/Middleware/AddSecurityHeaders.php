<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds standard browser security headers to every response, so API responses can't be framed, sniffed or leak referrers.
 *
 * JSON responses also get a Content-Security-Policy that forbids running any
 * content at all. HTML pages (the local API docs and debugging dashboard)
 * keep their own scripts working.
 */
final class AddSecurityHeaders
{
    /**
     * Sets the security headers on the outgoing response.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        }

        return $response;
    }
}
