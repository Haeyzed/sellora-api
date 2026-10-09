<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\ArchiveProduct;
use App\Tenant\Catalog\Exceptions\ProductStatusConflictException;
use App\Tenant\Catalog\Http\Requests\ManageProductRequest;
use App\Tenant\Catalog\Http\Resources\ProductResource;
use App\Tenant\Catalog\Models\Product;

/**
 * Taking a product off sale without deleting it.
 */
final class ArchiveProductController extends Controller
{
    /**
     * Archive a product.
     *
     * Needs the catalog.manage permission. Customers stop seeing it; it is
     * kept, still shows on old orders, and can be published again.
     *
     * @throws ProductStatusConflictException
     */
    public function __invoke(ManageProductRequest $request, Product $product, ArchiveProduct $archiveProduct): ProductResource
    {
        return new ProductResource($archiveProduct->handle($product)->load(ProductResource::RELATIONS));
    }
}
