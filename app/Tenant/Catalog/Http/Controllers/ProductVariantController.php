<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\UpdateProductVariant;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Http\Requests\UpdateProductVariantRequest;
use App\Tenant\Catalog\Http\Resources\ProductVariantResource;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * A product's variants: what is bought, with its price, codes and shipping details.
 */
final class ProductVariantController extends Controller
{
    /**
     * Change a variant.
     *
     * Needs the catalog.manage permission. Send only what changes. Amounts are
     * whole minor units of the store's base currency (1999 for 19.99); a
     * compare-at price must be higher than the price, and whatever ships
     * needs a weight.
     *
     * @throws VariantSkuTakenException
     */
    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant, UpdateProductVariant $updateProductVariant): ProductVariantResource
    {
        return new ProductVariantResource($updateProductVariant->handle($variant, $request->changes()));
    }
}
