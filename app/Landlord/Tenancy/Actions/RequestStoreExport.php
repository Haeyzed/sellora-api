<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\StoreExportStatus;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Jobs\BuildStoreExport;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccountReference;
use App\Shared\Tenancy\Exceptions\StoreExportInProgressException;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Support\ActivityLogger;

/**
 * Queues a full export of a store's data, for a platform admin with stores.export or for the store's owner.
 *
 * One export at a time per store: the store row is locked while checking, the
 * same lock a purge takes, and a partial unique index backs it up. The
 * export is built on the bulk queue and kept on the store's regional disk.
 */
final readonly class RequestStoreExport
{
    /**
     * @param  PlatformAdmin|null  $platformAdmin  The platform admin asking, or null when the owner does.
     *
     * @throws StoreStatusConflictException When the store has no database to export.
     * @throws StoreExportInProgressException When another export of the store is still being built.
     */
    public function handle(Tenant $store, AccountReference $requester, ?PlatformAdmin $platformAdmin = null): StoreExport
    {
        return StoreExport::query()->getConnection()->transaction(static function () use ($store, $requester, $platformAdmin): StoreExport {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [TenantStatus::Active, TenantStatus::Suspended, TenantStatus::Closed], true)) {
                throw new StoreStatusConflictException;
            }

            if (StoreExport::query()->where('tenant_id', $locked->id)->whereIn('status', StoreExportStatus::inProgress())->exists()) {
                throw new StoreExportInProgressException;
            }

            $storeExport = new StoreExport([
                'disk' => config()->string("tenancy.store_exports.disks.{$locked->hosting_region}", config()->string('tenancy.store_exports.default_disk')),
                'expires_at' => CarbonImmutable::now()->addDays(config()->integer('retention.periods.store_exports')),
            ]);
            $storeExport->forceFill([
                'tenant_id' => $locked->id,
                'requested_by_type' => $requester->type,
                'requested_by_id' => $requester->publicId,
                'status' => StoreExportStatus::Queued,
            ])->save();

            // Never the owner as causer: they live in the store's database, so the central log keeps them in requested_by instead.
            activity('stores')
                ->causedBy($platformAdmin)
                ->when($platformAdmin === null, static fn (ActivityLogger $activity): ActivityLogger => $activity->causedByAnonymous())
                ->performedOn($locked)
                ->event('store_export_requested')
                ->withProperties(['export' => $storeExport->public_id, 'requested_by' => ['type' => $requester->type, 'id' => $requester->publicId]])
                ->log("Requested an export of the store {$locked->name}");

            dispatch(new BuildStoreExport($storeExport->id))->afterCommit();

            return $storeExport;
        });
    }
}
