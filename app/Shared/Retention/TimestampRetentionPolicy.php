<?php

declare(strict_types=1);

namespace App\Shared\Retention;

use App\Shared\Retention\Contracts\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The common parent of retention policies that delete records by the age of one timestamp column.
 *
 * Deletes in small batches, so a large purge never locks a table for long
 * or slows down the store while it runs.
 *
 * @template TModel of Model
 */
abstract class TimestampRetentionPolicy implements RetentionPolicy
{
    private const int DELETE_BATCH_SIZE = 1000;

    /**
     * The records this policy cleans up.
     *
     * @return Builder<TModel>
     */
    abstract protected function query(): Builder;

    /**
     * The timestamp column whose age decides when a record is deleted.
     */
    abstract protected function timestampColumn(): string;

    /**
     * Deletes every record whose timestamp is older than the cutoff, batch by batch.
     */
    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $deletedCount = 0;

        do {
            $deletedInBatch = $this->query()
                ->where($this->timestampColumn(), '<', $cutoff)
                ->limit(self::DELETE_BATCH_SIZE)
                ->delete();

            $deletedCount += $deletedInBatch;
        } while ($deletedInBatch === self::DELETE_BATCH_SIZE);

        return $deletedCount;
    }
}
