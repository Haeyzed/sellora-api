<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\CreateProduct;
use App\Tenant\Catalog\Actions\UpdateProduct;
use App\Tenant\Catalog\Exceptions\ProductSlugTakenException;
use App\Tenant\Catalog\Exceptions\VariantSkuTakenException;
use App\Tenant\Catalog\Http\Requests\ListProductsRequest;
use App\Tenant\Catalog\Http\Requests\StoreProductRequest;
use App\Tenant\Catalog\Http\Requests\UpdateProductRequest;
use App\Tenant\Catalog\Http\Requests\ViewProductRequest;
use App\Tenant\Catalog\Http\Resources\ProductResource;
use App\Tenant\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's products and their variants, for staff managing the catalog.
 */
final class ProductController extends Controller
{
    /**
     * List products.
     *
     * Needs the catalog.view permission. Newest first. Narrow by status,
     * brand or category; send trashed=true for the products in the trash
     * instead.
     */
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $query = Product::query()->with(ProductResource::RELATIONS);
        $request->applyFilters($query);

        return ProductResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Add a product.
     *
     * Needs the catalog.manage permission. The product starts as a draft,
     * with its first variant. Amounts are whole minor units of the store's
     * base currency (1999 for 19.99); the price can wait, but customers can't
     * buy an unpriced variant. Refused when the plan's product limit
     * is reached; products in the trash don't count.
     *
     * @throws UsageLimitReachedException
     * @throws ProductSlugTakenException
     * @throws VariantSkuTakenException
     */
    public function store(StoreProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        $product = $createProduct->handle($request->productData(), $request->variantDetails());

        return (new ProductResource($product->load(ProductResource::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a product.
     *
     * Needs the catalog.view permission. Also shows a product in the trash.
     */
    public function show(ViewProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($product->load(ProductResource::RELATIONS));
    }

    /**
     * Change a product.
     *
     * Needs the catalog.manage permission. Send only what changes; for texts,
     * only the languages sent change. Categories sent replace the current
     * ones. Renaming doesn't change the slug. Prices and shipping details
     * belong to the variants.
     *
     * @throws ProductSlugTakenException
     */
    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $updateProduct): ProductResource
    {
        return new ProductResource($updateProduct->handle($product, $request->changes())->load(ProductResource::RELATIONS));
    }
}
