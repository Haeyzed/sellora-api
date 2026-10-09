<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\AddProductVariant;
use App\Tenant\Catalog\Actions\UpdateProductVariant;
use App\Tenant\Catalog\Exceptions\ProductHasNoOptionsException;
use App\Tenant\Catalog\Exceptions\ProductOptionsIncompleteException;
use App\Tenant\Catalog\Exceptions\VariantCombinationTakenException;
use App\Tenant\Catalog\Exceptions\VariantLimitReachedException;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Http\Requests\StoreProductVariantRequest;
use App\Tenant\Catalog\Http\Requests\UpdateProductVariantRequest;
use App\Tenant\Catalog\Http\Resources\ProductVariantResource;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A product's variants: what is bought, with its price, codes and shipping details.
 */
final class ProductVariantController extends Controller
{
    /**
     * Add a variant.
     *
     * Needs the catalog.manage permission. Only for a product with options:
     * send one value ID for each option, such as "Blue" and "XL"; no two
     * variants outside the trash may have the same values. Amounts are whole
     * minor units of the store's base currency (1999 for 19.99); the price can
     * wait, but customers can't buy an unpriced variant. A product has at most
     * 100 variants outside the trash.
     *
     * @throws ProductHasNoOptionsException
     * @throws ProductOptionsIncompleteException
     * @throws VariantLimitReachedException
     * @throws VariantCombinationTakenException
     * @throws VariantSkuTakenException
     */
    public function store(StoreProductVariantRequest $request, Product $product, AddProductVariant $addProductVariant): JsonResponse
    {
        $variant = $addProductVariant->handle($product, $request->chosenValues(), $request->details());

        return (new ProductVariantResource($variant->load(ProductVariantResource::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

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
        return new ProductVariantResource($updateProductVariant->handle($variant, $request->changes())->load(ProductVariantResource::RELATIONS));
    }
}
