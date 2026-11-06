<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Data;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Something a customer wants to save: a product customers can buy, and optionally one of its priced variants (checked by the request).
 */
final readonly class WishlistEntryData
{
    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
    ) {}

    /**
     * What makes it the same entry as one already saved: the product, and the variant or none.
     */
    public function key(): string
    {
        return $this->product->id.':'.($this->variant === null ? '' : $this->variant->id);
    }
}
