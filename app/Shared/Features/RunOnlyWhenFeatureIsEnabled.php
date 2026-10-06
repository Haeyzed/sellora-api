<?php

declare(strict_types=1);

namespace App\Shared\Features;

use Closure;

/**
 * Queued-job middleware that drops a module's job when the store can no longer use the module by the time the job runs.
 *
 * Plans change between dispatch and execution (a downgrade, a suspension), so
 * jobs check again when they run:
 *
 *     public function middleware(): array
 *     {
 *         return [new RunOnlyWhenFeatureIsEnabled('hr')];
 *     }
 *
 * A dropped job is finished, not failed, so it is not retried.
 */
final readonly class RunOnlyWhenFeatureIsEnabled
{
    public function __construct(private string $featureKey) {}

    /**
     * @param  Closure(object): mixed  $next
     */
    public function handle(object $job, Closure $next): mixed
    {
        if (! app(Features::class)->isEnabled($this->featureKey)) {
            return null;
        }

        return $next($job);
    }
}
