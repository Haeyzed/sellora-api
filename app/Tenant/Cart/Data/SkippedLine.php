<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Catalog\Models\ProductVariant;

/**
 * A guest cart's line that didn't come across when it was merged into the customer's cart, and why.
 */
final readonly class SkippedLine
{
    /**
     * @param  string  $reason  variant_not_available, variant_out_of_stock or cart_full.
     */
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
        public string $reason,
    ) {}
}
