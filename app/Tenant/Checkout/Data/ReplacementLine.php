<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * One line of a replacement order (Step 18c): the variant sent in place of a returned one, how many, and the most it may cost.
 */
final readonly class ReplacementLine
{
    /**
     * @param  Money|null  $atMost  For a free like-for-like replacement: what the returned units cost the buyer, which the line never costs more than (an allowance takes off the rest). Null for today's price with today's automatic promotions.
     */
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
        public ?Money $atMost = null,
    ) {}
}
