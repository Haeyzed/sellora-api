<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Enums\ProductLinkType;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductLink;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a product's links of one kind (related, upsell or cross-sell) with the products staff pick, in their order.
 *
 * A link may go to a product shoppers can't buy yet; the storefront leaves
 * it out until they can. Changes to one product's links queue on the
 * product's row lock, so two at once never mix: the last one wins whole.
 * A product never links to itself; the database refuses it too.
 */
final readonly class ReplaceProductLinks
{
    /**
     * @param  list<string>  $linkedProductIds  Public IDs, in order, each once, none of them the product's own: checked by the request.
     */
    public function handle(Product $product, ProductLinkType $type, array $linkedProductIds): Product
    {
        DB::transaction(static function () use ($product, $type, $linkedProductIds): void {
            Product::query()->whereKey($product->id)->lockForUpdate()->sole();

            ProductLink::query()->where('product_id', $product->id)->where('type', $type)->delete();

            // Read from the trash too: one that went there since the request was checked is kept, and hidden from shoppers like any trashed product.
            $ids = Product::withTrashed()->whereIn('public_id', $linkedProductIds)->pluck('id', 'public_id');

            foreach (array_values(array_filter(array_map(static fn (string $id): ?int => $ids->get($id), $linkedProductIds))) as $position => $linkedProductId) {
                ProductLink::query()->create([
                    'product_id' => $product->id,
                    'type' => $type,
                    'linked_product_id' => $linkedProductId,
                    'position' => $position,
                ]);
            }
        });

        return $product;
    }
}
