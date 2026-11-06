<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finds what a shopper asks to save: a product customers can buy, and optionally one of its priced variants outside the trash.
 *
 * @mixin FormRequest
 */
trait FindsWishlistEntries
{
    /**
     * The product, or null when customers can't buy it (a draft, unpriced, in the trash) or the store has none with this ID.
     */
    protected function buyableProduct(string $productId): ?Product
    {
        return Product::query()->visibleToCustomers()->where('public_id', $productId)->first();
    }

    /**
     * The variant of this product, or null when it is another product's, unpriced or in the trash.
     */
    protected function buyableVariantOf(Product $product, string $variantId): ?ProductVariant
    {
        return ProductVariant::query()->where('product_id', $product->id)->where('public_id', $variantId)->whereNotNull('price_amount')->first();
    }
}
