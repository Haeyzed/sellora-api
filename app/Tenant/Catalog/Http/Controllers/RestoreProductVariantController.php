<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\RestoreProductVariant;
use App\Tenant\Catalog\Exceptions\ProductStatusConflictException;
use App\Tenant\Catalog\Exceptions\VariantLimitReachedException;
use App\Tenant\Catalog\Exceptions\VariantRestoreConflictException;
use App\Tenant\Catalog\Http\Requests\ManageProductRequest;
use App\Tenant\Catalog\Http\Resources\ProductVariantResource;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Bringing a variant back from the trash.
 */
final class RestoreProductVariantController extends Controller
{
    /**
     * Restore a variant.
     *
     * Needs the catalog.manage permission. Refused when its values no longer
     * match the product's options, another variant now has its SKU or values,
     * the product has as many variants as allowed, or the product itself is in
     * the trash (restore the product instead). Restoring a variant that isn't
     * in the trash changes nothing.
     *
     * @throws VariantRestoreConflictException
     * @throws VariantLimitReachedException
     * @throws ProductStatusConflictException
     */
    public function __invoke(ManageProductRequest $request, Product $product, ProductVariant $variant, RestoreProductVariant $restoreProductVariant): ProductVariantResource
    {
        return new ProductVariantResource($restoreProductVariant->handle($variant)->load(ProductVariantResource::RELATIONS));
    }
}
