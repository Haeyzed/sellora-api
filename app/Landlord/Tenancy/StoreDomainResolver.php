<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Exceptions\StoreUnavailableException;
use App\Landlord\Tenancy\Models\Tenant;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

/**
 * Finds the store a request is for from its domain, and refuses stores that aren't serving requests, such as one still being set up.
 *
 * Checked before tenancy starts, so a request never reaches a store whose
 * database doesn't exist yet.
 */
final class StoreDomainResolver extends DomainTenantResolver
{
    /**
     * @throws StoreUnavailableException When the store isn't active.
     */
    public function resolve(mixed ...$args): TenantContract
    {
        $tenant = parent::resolve(...$args);

        if (! $tenant instanceof Tenant || ! $tenant->status->servesRequests()) {
            throw new StoreUnavailableException;
        }

        return $tenant;
    }
}
