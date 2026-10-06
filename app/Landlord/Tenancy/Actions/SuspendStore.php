<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Features;
use Carbon\CarbonImmutable;

/**
 * Suspends an open store, for example for fraud: every feature becomes suspended, whatever its subscription.
 *
 * Nothing is deleted. Staff can still sign in, customers see a friendly
 * unavailable response, and what was already started can be finished.
 * Paying the subscription doesn't lift it; only a platform admin does.
 */
final readonly class SuspendStore
{
    public function __construct(private Features $features) {}

    /**
     * @param  string  $reason  Why, for the platform team.
     *
     * @throws StoreStatusConflictException When the store isn't open.
     */
    public function handle(PlatformAdmin $actor, Tenant $store, string $reason): Tenant
    {
        $store = Tenant::query()->getConnection()->transaction(static function () use ($actor, $store, $reason): Tenant {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TenantStatus::Active) {
                throw new StoreStatusConflictException;
            }

            $locked->forceFill(['status' => TenantStatus::Suspended, 'suspended_at' => CarbonImmutable::now(), 'suspension_reason' => $reason])->save();

            activity('stores')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('store_suspended')
                ->withProperties(['reason' => $reason])
                ->log("Suspended the store {$locked->name}");

            return $locked;
        });

        $this->features->forget($store->id);

        return $store;
    }
}
