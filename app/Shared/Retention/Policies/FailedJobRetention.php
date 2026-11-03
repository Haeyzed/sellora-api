<?php

declare(strict_types=1);

namespace App\Shared\Retention\Policies;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;

/**
 * Deletes old failed queued jobs from the central database, where every store's failed jobs are kept: each holds the job's data, which can name a buyer.
 */
final readonly class FailedJobRetention implements RetentionPolicy
{
    public function __construct(private FailedJobProviderInterface $failedJobs) {}

    public function periodKey(): string
    {
        return 'failed_jobs';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Central;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        return $this->failedJobs instanceof PrunableFailedJobProvider ? $this->failedJobs->prune($cutoff) : 0;
    }
}
