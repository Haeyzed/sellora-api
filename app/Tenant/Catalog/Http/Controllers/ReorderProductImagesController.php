<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\ReorderProductImages;
use App\Tenant\Catalog\Exceptions\ProductImageOrderMismatchException;
use App\Tenant\Catalog\Http\Requests\ReorderProductImagesRequest;
use App\Tenant\Catalog\Http\Resources\ProductResource;
use App\Tenant\Catalog\Models\Product;

/**
 * The order of a product's gallery.
 */
final class ReorderProductImagesController extends Controller
{
    /**
     * Reorder a product's images.
     *
     * Needs the catalog.manage permission. List every image of the gallery
     * once, in the new order; the first is shown in lists.
     *
     * @throws ProductImageOrderMismatchException
     */
    public function __invoke(ReorderProductImagesRequest $request, Product $product, ReorderProductImages $reorderProductImages): ProductResource
    {
        $reorderProductImages->handle($product, $request->imageIds());

        return new ProductResource($product->load(ProductResource::RELATIONS));
    }
}
