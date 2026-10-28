<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DriverLocation;
use Carbon\CarbonImmutable;

/**
 * Deletes drivers' locations once their delivery ended (delivered, or failed and resolved) longer ago than the retention period, in every store, in small batches. A delivery still under way, or failed and unresolved, keeps its trail.
 */
final class DriverLocationRetention implements RetentionPolicy
{
    private const int DELETE_BATCH_SIZE = 1000;

    public function periodKey(): string
    {
        return 'driver_locations';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $ended = Delivery::query()->select('id')->whereRaw('coalesce(delivered_at, resolved_at) < ?', [$cutoff]);
        $deletedCount = 0;

        do {
            $deletedInBatch = DriverLocation::query()->whereIn('delivery_id', $ended)->limit(self::DELETE_BATCH_SIZE)->delete();
            $deletedCount += $deletedInBatch;
        } while ($deletedInBatch === self::DELETE_BATCH_SIZE);

        return $deletedCount;
    }
}
