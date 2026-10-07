<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * For the few endpoints served on both the central domain and every store's domain (the geography reference data).
 *
 * On a central domain the request goes through without a store. Anywhere
 * else the store is identified from the domain exactly as for store routes,
 * so an unknown domain gets a 404 and a store that doesn't serve requests
 * gets a 503.
 */
final readonly class IdentifyStoreUnlessCentral
{
    public function __construct(private InitializeTenancyByDomain $initializeTenancyByDomain) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), config()->array('tenancy.central_domains'), true)) {
            return $next($request);
        }

        /** @var Response */
        return $this->initializeTenancyByDomain->handle($request, $next);
    }
}
