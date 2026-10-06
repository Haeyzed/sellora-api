<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Jobs\ProvisionStore;
use App\Landlord\Tenancy\Models\Tenant;

/**
 * Queues setting up a store again after every try failed, for example once more database capacity was added.
 *
 * Every setup step is safe to repeat, so it carries on from where it stopped.
 */
final readonly class RetryStoreProvisioning
{
    /**
     * @throws StoreStatusConflictException When the store's setup hasn't failed.
     */
    public function handle(PlatformAdmin $actor, Tenant $store): Tenant
    {
        $store = Tenant::query()->getConnection()->transaction(static function () use ($actor, $store): Tenant {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TenantStatus::ProvisioningFailed) {
                throw new StoreStatusConflictException;
            }

            $locked->forceFill(['status' => TenantStatus::Provisioning])->save();

            activity('stores')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('store_provisioning_retried')
                ->log("Retried setting up the store {$locked->name}");

            return $locked;
        });

        ProvisionStore::dispatch($store->id);

        return $store->refresh();
    }
}
