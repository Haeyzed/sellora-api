<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\PublishProduct;
use App\Tenant\Catalog\Exceptions\ProductCannotBePublishedException;
use App\Tenant\Catalog\Exceptions\ProductStatusConflictException;
use App\Tenant\Catalog\Http\Requests\ManageProductRequest;
use App\Tenant\Catalog\Http\Resources\ProductResource;
use App\Tenant\Catalog\Models\Product;

/**
 * Putting a product on sale.
 */
final class PublishProductController extends Controller
{
    /**
     * Publish a product.
     *
     * Needs the catalog.manage permission. Makes a draft or archived product
     * visible to customers. It needs a name in the store's default language
     * and at least one priced variant; customers only ever see and buy priced
     * variants.
     *
     * @throws ProductCannotBePublishedException
     * @throws ProductStatusConflictException
     */
    public function __invoke(ManageProductRequest $request, Product $product, PublishProduct $publishProduct): ProductResource
    {
        return new ProductResource($publishProduct->handle($product)->load(ProductResource::RELATIONS));
    }
}
