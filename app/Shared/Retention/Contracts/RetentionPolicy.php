<?php

declare(strict_types=1);

namespace App\Shared\Retention\Contracts;

use App\Shared\Retention\RetentionScope;
use Carbon\CarbonImmutable;

/**
 * Deletes one kind of record once it is older than its retention period, so personal data is never kept longer than needed.
 *
 * Each policy names its period in config/retention.php; the purge job works
 * out the cutoff date and asks the policy to delete everything older.
 */
interface RetentionPolicy
{
    /**
     * The key of this policy's period in config/retention.php, for example "activity_log".
     */
    public function periodKey(): string;

    /**
     * Which databases this policy cleans.
     */
    public function scope(): RetentionScope;

    /**
     * Deletes every record older than the cutoff in the current database.
     *
     * @return int How many records were deleted.
     */
    public function purgeOlderThan(CarbonImmutable $cutoff): int;
}
