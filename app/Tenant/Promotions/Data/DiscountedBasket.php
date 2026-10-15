<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Tenant\Pricing\Data\PricedBasket;

/**
 * Lines priced with their discounts and tax, and which discounts they got.
 */
final readonly class DiscountedBasket
{
    public function __construct(
        public PricedBasket $priced,
        public DiscountOutcome $discounts,
    ) {}
}
