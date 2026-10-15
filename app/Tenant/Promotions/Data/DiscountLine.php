<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * One line a discount source looks at: a variant, how many, and what it costs before any discount.
 */
final readonly class DiscountLine
{
    /**
     * @param  string  $key  The line's key, as in the PricingRequest.
     * @param  Money  $unitPrice  In the basket's currency, read through the store's tax mode.
     * @param  bool  $isOnSale  It has a compare-at price; promotions skip it unless they include sale items.
     */
    public function __construct(
        public string $key,
        public ProductVariant $variant,
        public int $quantity,
        public Money $unitPrice,
        public bool $isOnSale,
    ) {}

    public function subtotal(): Money
    {
        return $this->unitPrice->multiplyBy($this->quantity);
    }
}
