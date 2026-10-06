<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Features;

/**
 * Removes one store's override of a usage limit, so its plan's limit applies again.
 */
final readonly class RemoveLimitOverride
{
    public function __construct(private Features $features) {}

    public function handle(PlatformAdmin $actor, Tenant $store, string $limitKey): void
    {
        $limitOverride = TenantLimitOverride::query()->where('tenant_id', $store->id)->where('limit_key', $limitKey)->first();

        if ($limitOverride === null) {
            return;
        }

        // Deleted one by one, not with a query, so the audit trail records it.
        $limitOverride->delete();

        activity('stores')
            ->causedBy($actor)
            ->performedOn($store)
            ->event('limit_override_removed')
            ->withProperties(['limit' => $limitKey])
            ->log("Removed the {$limitKey} limit override of the store {$store->name}");

        $this->features->forget($store->id);
    }
}
