<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\ProductImageOrderMismatchException;
use App\Tenant\Catalog\Models\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Puts a product's gallery in a new order; the first image is the one shown in lists. Every image of the gallery must be listed, once.
 */
final readonly class ReorderProductImages
{
    /**
     * @param  list<string>  $imageIds  Every gallery image's ID, in the new order.
     *
     * @throws ProductImageOrderMismatchException When the list isn't exactly the gallery's images.
     */
    public function handle(Product $product, array $imageIds): Product
    {
        $current = $product->getMedia(Product::GALLERY)->pluck('id', 'uuid')->all();
        $sorted = $imageIds;
        sort($sorted);
        $known = array_keys($current);
        sort($known);

        if ($sorted !== $known) {
            throw new ProductImageOrderMismatchException;
        }

        Media::setNewOrder(array_map(static fn (string $uuid): int => (int) $current[$uuid], $imageIds));

        return $product;
    }
}
