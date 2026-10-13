<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Actions\AssignTaxClass;
use App\Tenant\Pricing\Http\Requests\AssignTaxClassRequest;
use App\Tenant\Pricing\Http\Requests\ViewPricingRequest;
use App\Tenant\Pricing\Http\Resources\VariantPricingResource;
use App\Tenant\Pricing\Services\VariantPricingDetails;
use Illuminate\Http\Response;

/**
 * How each variant is priced beyond its base price.
 */
final class VariantPricingController extends Controller
{
    /**
     * Show a variant's pricing.
     *
     * Needs the pricing.view permission. Its tax class, and whether that is
     * the store's default.
     */
    public function show(ViewPricingRequest $request, ProductVariant $variant, VariantPricingDetails $variantPricingDetails): VariantPricingResource
    {
        return new VariantPricingResource($variantPricingDetails->of($variant));
    }

    /**
     * Change a variant's tax class.
     *
     * Needs the pricing.manage permission. Null puts it back in the store's
     * default class. Audited.
     */
    public function update(AssignTaxClassRequest $request, ProductVariant $variant, AssignTaxClass $assignTaxClass, VariantPricingDetails $variantPricingDetails): VariantPricingResource
    {
        $assignTaxClass->toVariant($variant, $request->taxClass());

        return new VariantPricingResource($variantPricingDetails->of($variant));
    }

    /**
     * Change the tax class of a product's variants.
     *
     * Needs the pricing.manage permission. Every variant of the product,
     * including those in the trash; null puts them back in the store's
     * default class. Audited per variant.
     */
    public function updateProduct(AssignTaxClassRequest $request, Product $product, AssignTaxClass $assignTaxClass): Response
    {
        $assignTaxClass->toProduct($product, $request->taxClass());

        return response()->noContent();
    }
}
