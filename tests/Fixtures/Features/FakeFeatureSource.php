<?php

declare(strict_types=1);

namespace Tests\Fixtures\Features;

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureSnapshot;

/**
 * A feature source with hand-picked plans per store, which counts how often Features asks it.
 */
final class FakeFeatureSource implements FeatureSource
{
    public int $timesAsked = 0;

    /**
     * @var array<string, FeatureSnapshot>
     */
    private array $snapshots = [];

    public function give(string $tenantId, FeatureSnapshot $snapshot): self
    {
        $this->snapshots[$tenantId] = $snapshot;

        return $this;
    }

    public function snapshotFor(string $tenantId): FeatureSnapshot
    {
        $this->timesAsked++;

        return $this->snapshots[$tenantId] ?? FeatureSnapshot::withoutPlan();
    }

    /**
     * @param  list<string>  $entitled
     * @param  list<string>  $removed
     * @param  list<string>  $merchantDisabled
     * @param  array<string, int|null>  $limits
     */
    public static function snapshot(
        array $entitled = [],
        array $removed = [],
        array $merchantDisabled = [],
        array $limits = [],
        bool $isStoreSuspended = false,
    ): FeatureSnapshot {
        return new FeatureSnapshot($isStoreSuspended, $entitled, $removed, $merchantDisabled, $limits);
    }
}
