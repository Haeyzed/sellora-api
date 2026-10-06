<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Features;

/**
 * Lifts a platform suspension. A store whose subscription is also suspended stays suspended until that is paid.
 */
final readonly class ReactivateStore
{
    public function __construct(private Features $features) {}

    /**
     * @throws StoreStatusConflictException When the store isn't suspended by the platform.
     */
    public function handle(PlatformAdmin $actor, Tenant $store): Tenant
    {
        $store = Tenant::query()->getConnection()->transaction(static function () use ($actor, $store): Tenant {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TenantStatus::Suspended) {
                throw new StoreStatusConflictException;
            }

            $locked->forceFill(['status' => TenantStatus::Active, 'suspended_at' => null, 'suspension_reason' => null])->save();

            activity('stores')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('store_reactivated')
                ->log("Reactivated the store {$locked->name}");

            return $locked;
        });

        $this->features->forget($store->id);

        return $store;
    }
}
