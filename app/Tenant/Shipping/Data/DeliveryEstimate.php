<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

use Carbon\CarbonImmutable;

/**
 * When an order would arrive (or be ready to collect), as dates in the store's timezone, counting only the store's working days.
 */
final readonly class DeliveryEstimate
{
    /**
     * @param  CarbonImmutable  $earliest  The date, in the store's timezone.
     * @param  CarbonImmutable  $latest  The date, in the store's timezone.
     */
    public function __construct(
        public int $minDays,
        public int $maxDays,
        public CarbonImmutable $earliest,
        public CarbonImmutable $latest,
    ) {}
}
