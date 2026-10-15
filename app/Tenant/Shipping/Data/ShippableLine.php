<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * A line of a basket being quoted for shipping: what it is, how many, and what it is worth.
 */
final readonly class ShippableLine
{
    /**
     * @param  Money  $value  The line's value after discounts, as displayed (with tax in a tax-inclusive store), in the quote's currency.
     */
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
        public Money $value,
    ) {}
}
