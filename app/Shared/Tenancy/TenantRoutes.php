<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * How every store API route is served: on store domains only, under /api/v1, with the store identified from the domain.
 *
 * Core store routes and module and integration routes all use this, so they
 * can never drift apart.
 */
final class TenantRoutes
{
    public const string PREFIX = 'api/v1';

    /**
     * @var list<string>
     */
    public const array MIDDLEWARE = ['api', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class];
}
