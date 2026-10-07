<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Jobs;

use App\Landlord\Subscriptions\Models\FeatureGrant;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Domain;
use App\Landlord\Tenancy\Models\ReleasedSubdomain;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\StoreDatabase;
use App\Landlord\Tenancy\StoreExportRetention;
use App\Shared\Features\Features;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;

/**
 * Deletes a closed store's data for good, once its purge date has passed and purging is switched on.
 *
 * Resumable and safe to run twice. It first locks the store, checks again
 * and marks it Purging, which commits before anything is deleted: from then
 * on a restore is refused, and a purge that stops halfway carries on from
 * where it was. Then, each step doing nothing if already done: drop the
 * database, delete the store's files and exports, delete its domains and hold
 * its subdomain, clear the owner's personal data and every free-text reason,
 * and mark it Purged. Its subscriptions, feature grants, limit overrides and
 * the legal acceptances (the contract) are kept.
 */
final class PurgeStore implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly string $tenantId)
    {
        $this->onQueue(config()->string('retention.queue'));
    }

    public function handle(StoreDatabase $storeDatabase, StoreExportRetention $storeExportRetention, Features $features): void
    {
        $store = $this->startPurging();

        if ($store === null) {
            return;
        }

        $storeDatabase->drop($store);
        $this->deleteFiles($store);
        $this->deleteExports($store, $storeExportRetention);
        $this->releaseDomains($store);
        $this->finish($store);
        $features->forget($store->id);
    }

    /**
     * Marks the store Purging, or picks up a purge that stopped halfway. Null when it must not be purged.
     */
    private function startPurging(): ?Tenant
    {
        return Tenant::query()->getConnection()->transaction(function (): ?Tenant {
            $store = Tenant::query()->whereKey($this->tenantId)->lockForUpdate()->first();

            if ($store === null || $store->status === TenantStatus::Purged) {
                return null;
            }

            if ($store->status === TenantStatus::Purging) {
                return $store;
            }

            if (! $this->isDueForPurging($store)) {
                return null;
            }

            $store->forceFill(['status' => TenantStatus::Purging])->save();

            return $store;
        });
    }

    private function isDueForPurging(Tenant $store): bool
    {
        return config()->boolean('tenancy.purge_enabled')
            && $store->status === TenantStatus::Closed
            && $store->purge_after !== null
            && ! $store->purge_after->isFuture();
    }

    /**
     * The store's own folder of files (each store's local storage is kept apart by tenancy).
     */
    private function deleteFiles(Tenant $store): void
    {
        File::deleteDirectory(storage_path(config()->string('tenancy.filesystem.suffix_base').$store->getTenantKey()));
    }

    private function deleteExports(Tenant $store, StoreExportRetention $storeExportRetention): void
    {
        foreach (StoreExport::query()->where('tenant_id', $store->id)->lazyById(100) as $storeExport) {
            $storeExportRetention->delete($storeExport);
        }
    }

    /**
     * Deletes every domain of the store, holding its platform subdomain so nobody can take over its old links.
     */
    private function releaseDomains(Tenant $store): void
    {
        $platformSuffix = '.'.config()->string('platform.domain');
        $now = CarbonImmutable::now();

        Domain::query()->getConnection()->transaction(static function () use ($store, $platformSuffix, $now): void {
            foreach (Domain::query()->where('tenant_id', $store->id)->get() as $domain) {
                if (str_ends_with($domain->domain, $platformSuffix)) {
                    ReleasedSubdomain::query()->updateOrCreate(
                        ['subdomain' => substr($domain->domain, 0, -strlen($platformSuffix))],
                        ['released_at' => $now, 'held_until' => $now->addDays(config()->integer('tenancy.released_subdomain_hold_days'))],
                    );
                }

                $domain->delete();
            }
        });
    }

    /**
     * Clears the owner's personal data, and the free-text reasons that may hold some (on the store, its feature grants and its limit overrides), then marks the store Purged.
     */
    private function finish(Tenant $store): void
    {
        Tenant::query()->getConnection()->transaction(static function () use ($store): void {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => TenantStatus::Purged,
                'purged_at' => CarbonImmutable::now(),
                'owner_name' => null,
                'owner_email' => null,
                'closure_reason' => null,
                'suspension_reason' => null,
            ])->save();

            // Mass updates on purpose: an audited save would copy each reason into the audit trail as the old value.
            FeatureGrant::query()->where('tenant_id', $locked->id)->whereNotNull('reason')->update(['reason' => null]);
            TenantLimitOverride::query()->where('tenant_id', $locked->id)->whereNotNull('reason')->update(['reason' => null]);

            activity('stores')
                ->causedByAnonymous()
                ->performedOn($locked)
                ->event('store_purged')
                ->log("Purged the store {$locked->name}");
        });
    }
}
