<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;

/**
 * Puts a product in its categories and picks the one it is mainly listed under.
 *
 * The primary category is always one of the product's categories (a
 * foreign key checks it when the transaction commits).
 */
final readonly class ProductCategories
{
    /**
     * Replaces the product's categories. The primary category is the one chosen, else the current one if it stays, else the first.
     *
     * @param  list<Category>  $categories
     */
    public function replace(Product $product, array $categories, ?Category $primaryCategory): void
    {
        $categoryIds = array_values(array_unique(array_map(static fn (Category $category): int => $category->id, $categories)));

        $product->primary_category_id = match (true) {
            $categoryIds === [] => null,
            $primaryCategory !== null => $primaryCategory->id,
            in_array($product->primary_category_id, $categoryIds, true) => $product->primary_category_id,
            default => $categoryIds[0],
        };

        $product->save();
        $product->categories()->sync($categoryIds);
    }
}
