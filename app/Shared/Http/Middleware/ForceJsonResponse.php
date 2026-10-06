<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Treats every request as an API request, so clients always get JSON back, even when they forget the Accept header.
 *
 * Without this, a request missing "Accept: application/json" can be answered
 * with an HTML page or a redirect to a login page that does not exist.
 */
final class ForceJsonResponse
{
    /**
     * Marks the request as accepting JSON before anything else handles it.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
