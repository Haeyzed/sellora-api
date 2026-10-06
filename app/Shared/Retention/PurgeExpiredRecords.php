<?php

declare(strict_types=1);

namespace App\Shared\Retention;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

/**
 * Deletes expired personal data from one database: a single store's, or the platform's central one.
 *
 * One job is dispatched per store, so a failure in one store never stops
 * other stores from being cleaned. Dispatched inside a store's context, the
 * job runs in that store's context too (tenancy restores it on the worker).
 */
final class PurgeExpiredRecords implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue(config()->string('retention.queue'));
    }

    /**
     * Runs every retention policy that applies to the current database.
     *
     * @throws LogicException When a policy has no retention period configured.
     */
    public function handle(RetentionRegistry $registry): void
    {
        $isTenantContext = tenancy()->initialized;

        foreach ($registry->policiesFor($isTenantContext) as $policy) {
            $policy->purgeOlderThan($this->cutoffFor($policy->periodKey()));
        }
    }

    private function cutoffFor(string $periodKey): CarbonImmutable
    {
        $periodInDays = config('retention.periods.'.$periodKey);

        if (! is_int($periodInDays) || $periodInDays < 0) {
            throw new LogicException("No retention period is configured for [{$periodKey}] in config/retention.php.");
        }

        return CarbonImmutable::now()->subDays($periodInDays);
    }
}
