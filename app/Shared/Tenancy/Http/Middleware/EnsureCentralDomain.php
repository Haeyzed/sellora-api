<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes platform-only endpoints (platform admin API, webhooks) unreachable from store domains.
 *
 * A request on any host that is not a configured central domain gets a 404,
 * as if the endpoint did not exist.
 */
final class EnsureCentralDomain
{
    /**
     * Lets the request through only when it arrived on a central domain.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);

        abort_unless(in_array($request->getHost(), $centralDomains, true), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
