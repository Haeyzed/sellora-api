<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Features\Features;
use App\Shared\Tenancy\Contracts\StoreSessions;

/**
 * Reopens a closed store before its purge date, returning it to the status it had before closing.
 *
 * A store suspended before closing comes back suspended, so closing and
 * restoring can't lift a suspension. Restoring takes the same row lock as a
 * purge, so the two can never overlap. Any sign-in left from before the store
 * closed is revoked, so an old token never comes back to life.
 */
final readonly class RestoreStore
{
    public function __construct(
        private StoreSessions $storeSessions,
        private Features $features,
    ) {}

    /**
     * @throws StoreStatusConflictException When the store isn't closed, for example because it is already being purged.
     */
    public function handle(PlatformAdmin $actor, Tenant $store): Tenant
    {
        $store = Tenant::query()->getConnection()->transaction(function () use ($actor, $store): Tenant {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TenantStatus::Closed || $locked->status_before_closing === null) {
                throw new StoreStatusConflictException;
            }

            $restoredStatus = $locked->status_before_closing;

            if ($restoredStatus->servesRequests()) {
                $locked->run(fn () => $this->storeSessions->endAll());
            }

            $locked->forceFill([
                'status' => $restoredStatus,
                'status_before_closing' => null,
                'closed_at' => null,
                'closure_reason' => null,
                'closed_by_type' => null,
                'closed_by_id' => null,
                'purge_after' => null,
                'purge_reminder_sent_at' => null,
            ])->save();

            activity('stores')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('store_restored')
                ->withProperties(['status' => $restoredStatus->value])
                ->log("Restored the store {$locked->name}");

            return $locked;
        });

        $this->features->forget($store->id);

        return $store;
    }
}
