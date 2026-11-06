<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Storefront\Data\WishlistEntryData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding the list a shopper kept in their browser as a guest to their own wishlist, once they sign in.
 */
final class MergeIntoWishlistRequest extends FormRequest
{
    use ActsAsCustomer;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * What the shopper saved as a guest, oldest first. An entry for a product (or variant) the store no longer sells is skipped without an error, since a browser's list can be old.
             *
             * @var list<array{product_id: string, variant_id?: string|null}>
             */
            'items' => ['present', 'list', 'max:'.config()->integer('storefront.wishlist_max_items')],
            'items.*' => ['required', 'array:product_id,variant_id'],
            'items.*.product_id' => ['required', 'string', 'ulid'],
            'items.*.variant_id' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * The entries for what customers can still buy, in the order sent (repeats are saved once, by WishlistEntries).
     *
     * @return list<WishlistEntryData>
     */
    public function entries(): array
    {
        $asked = [];

        foreach ($this->array('items') as $item) {
            if (is_array($item) && is_string($item['product_id'] ?? null)) {
                $asked[] = ['product' => $item['product_id'], 'variant' => is_string($item['variant_id'] ?? null) ? $item['variant_id'] : null];
            }
        }

        // Two queries for the whole list, however long.
        $products = Product::query()->visibleToCustomers()->whereIn('public_id', array_column($asked, 'product'))->get()->keyBy('public_id');
        $variants = ProductVariant::query()->whereIn('public_id', array_filter(array_column($asked, 'variant')))->whereNotNull('price_amount')->get()->keyBy('public_id');
        $entries = [];

        foreach ($asked as ['product' => $productId, 'variant' => $variantId]) {
            $product = $products->get($productId);
            $variant = $variantId === null ? null : $variants->get($variantId);

            if ($product === null || ($variantId !== null && $variant?->product_id !== $product->id)) {
                continue;
            }

            $entries[] = new WishlistEntryData($product, $variant);
        }

        return $entries;
    }
}
