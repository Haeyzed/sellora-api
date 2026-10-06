<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use Carbon\CarbonImmutable;

/**
 * What the owner is told after closing their store: when it closed, and when its data will be deleted for good.
 */
final readonly class ClosedStore
{
    public function __construct(
        public CarbonImmutable $closedAt,
        public CarbonImmutable $purgeAfter,
    ) {}
}
