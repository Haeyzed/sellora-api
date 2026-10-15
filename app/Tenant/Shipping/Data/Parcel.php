<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

/**
 * What a carrier is asked to move: one parcel with everything that ships, by its total weight.
 */
final readonly class Parcel
{
    public function __construct(
        public int $weightGrams,
    ) {}
}
