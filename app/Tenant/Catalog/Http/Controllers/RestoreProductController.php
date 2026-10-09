<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\RestoreProduct;
use App\Tenant\Catalog\Exceptions\ProductRestoreConflictException;
use App\Tenant\Catalog\Http\Requests\ManageProductRequest;
use App\Tenant\Catalog\Http\Resources\ProductResource;
use App\Tenant\Catalog\Models\Product;

/**
 * Bringing a product back from the trash.
 */
final class RestoreProductController extends Controller
{
    /**
     * Restore a product.
     *
     * Needs the catalog.manage permission. It comes back as a draft, with the
     * variants that went to the trash with it. Refused when the plan's product
     * limit is reached, or another product now uses its slug or another
     * variant one of its SKUs. Restoring a product that isn't in the trash
     * changes nothing.
     *
     * @throws UsageLimitReachedException
     * @throws ProductRestoreConflictException
     */
    public function __invoke(ManageProductRequest $request, Product $product, RestoreProduct $restoreProduct): ProductResource
    {
        return new ProductResource($restoreProduct->handle($product)->load(ProductResource::RELATIONS));
    }
}
