<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\ChangeProductOptions;
use App\Tenant\Catalog\Exceptions\ProductOptionsIncompleteException;
use App\Tenant\Catalog\Exceptions\VariantCombinationTakenException;
use App\Tenant\Catalog\Http\Requests\ChangeProductOptionsRequest;
use App\Tenant\Catalog\Http\Resources\ProductResource;
use App\Tenant\Catalog\Models\Product;

/**
 * Which attributes a product's variants differ by.
 */
final class ProductOptionsController extends Controller
{
    /**
     * Set a product's options.
     *
     * Needs the catalog.manage permission. Sets the attributes its variants
     * differ by, such as Size and Colour, and in the same step gives every
     * variant outside the trash one value for each: list them all. This is
     * how a product with one variant becomes one with many: give it options
     * and its variant values, then add the other variants. Existing variants
     * keep their IDs, SKUs and prices. No two variants may end up with the
     * same values; going back to no options needs a single variant.
     *
     * @throws ProductOptionsIncompleteException
     * @throws VariantCombinationTakenException
     */
    public function __invoke(ChangeProductOptionsRequest $request, Product $product, ChangeProductOptions $changeProductOptions): ProductResource
    {
        $changeProductOptions->handle($product, $request->chosenAttributes(), $request->valuesByVariantId());

        return new ProductResource($product->load(ProductResource::RELATIONS));
    }
}
