<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Jobs\PurgeStore;
use App\Landlord\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues the purge of every closed store past its purge date, and of any purge that stopped halfway.
 *
 * Scheduled daily. Does nothing while tenancy.purge_enabled is off, which it
 * stays in production until per-region encrypted backups exist and a restore
 * has been tested.
 */
final class PurgeClosedStoresCommand extends Command
{
    protected $signature = 'stores:purge-closed';

    protected $description = 'Delete the data of closed stores past their purge date, when purging is switched on';

    public function handle(): int
    {
        if (! config()->boolean('tenancy.purge_enabled')) {
            $this->components->warn('Purging closed stores is switched off (tenancy.purge_enabled). Nothing was purged.');

            return self::SUCCESS;
        }

        $queued = 0;

        foreach ($this->storesToPurge()->lazyById(100) as $store) {
            dispatch(new PurgeStore($store->id));
            $queued++;
        }

        $this->components->info("Queued the purge of {$queued} stores.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Tenant>
     */
    private function storesToPurge(): Builder
    {
        return Tenant::query()->where(static function (Builder $query): void {
            $query->where(static function (Builder $query): void {
                $query->where('status', TenantStatus::Closed)->where('purge_after', '<=', CarbonImmutable::now());
            })->orWhere('status', TenantStatus::Purging);
        });
    }
}
