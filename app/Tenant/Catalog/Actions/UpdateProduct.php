<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\ProductData;
use App\Tenant\Catalog\Exceptions\ProductSlugTakenException;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Services\ProductCategories;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Changes a product's name, description, slug, brand or categories. Languages that aren't sent keep their text.
 *
 * Renaming doesn't change the slug, so links keep working; staff change the
 * slug on purpose.
 */
final readonly class UpdateProduct
{
    public function __construct(private ProductCategories $productCategories) {}

    /**
     * @throws ProductSlugTakenException When the new slug is used by another product outside the trash.
     */
    public function handle(Product $product, ProductData $changes): Product
    {
        return Product::query()->getConnection()->transaction(function () use ($product, $changes): Product {
            $this->applyTexts($product, $changes);

            if ($changes->removesBrand) {
                $product->brand_id = null;
            } elseif ($changes->brand !== null) {
                $product->brand_id = $changes->brand->id;
            }

            try {
                $product->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new ProductSlugTakenException(previous: $exception);
            }

            if ($changes->categories !== null) {
                $this->productCategories->replace($product, $changes->categories, $changes->primaryCategory);
            }

            return $product;
        });
    }

    private function applyTexts(Product $product, ProductData $changes): void
    {
        if ($changes->name !== null) {
            $product->changeTranslations('name', $changes->name);
        }

        if ($changes->removesDescription) {
            $product->description = null;
        } elseif ($changes->description !== null) {
            $product->changeTranslations('description', $changes->description);
        }

        if ($changes->slug !== null) {
            $product->slug = $changes->slug;
        }
    }
}
