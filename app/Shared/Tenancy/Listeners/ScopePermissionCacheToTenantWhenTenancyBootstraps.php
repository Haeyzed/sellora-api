<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Listeners;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Events\TenancyBootstrapped;

/**
 * Gives each store its own cache of staff roles and permissions, so one store's roles never leak into another's.
 *
 * The permission registrar keeps the cache store it was built with, which is not
 * tagged by tenancy, so the cache key itself must carry the tenant.
 */
final readonly class ScopePermissionCacheToTenantWhenTenancyBootstraps
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    /**
     * Switches the permission cache to the store that has just been opened.
     */
    public function handle(TenancyBootstrapped $event): void
    {
        $tenant = $event->tenancy->tenant;

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->permissionRegistrar->initializeCache();
        $this->permissionRegistrar->cacheKey .= '.tenant.'.$tenant->getTenantKey();
    }
}
