<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Shared\Money\Money;

/**
 * A line's unit price moved since the shopper last saw it: what it was and what it is now, both in the cart's currency at today's rate.
 */
final readonly class PriceChange
{
    public function __construct(
        public Money $was,
        public Money $now,
    ) {}
}
