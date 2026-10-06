<?php

declare(strict_types=1);

namespace Tests\Fixtures\Features;

use App\Shared\Features\RunOnlyWhenFeatureIsEnabled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A stand-in module job that records each time it actually runs.
 */
final class SampleLoyaltyJob implements ShouldQueue
{
    use Queueable;

    public static int $timesRun = 0;

    public function handle(): void
    {
        self::$timesRun++;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunOnlyWhenFeatureIsEnabled('loyalty')];
    }
}
