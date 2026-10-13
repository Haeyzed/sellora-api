<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Actions\AssignTaxClass;
use App\Tenant\Pricing\Actions\SetFixedPrice;
use App\Tenant\Pricing\Http\Requests\AssignTaxClassRequest;
use App\Tenant\Pricing\Http\Requests\ManagePricingRequest;
use App\Tenant\Pricing\Http\Requests\SetFixedPriceRequest;
use App\Tenant\Pricing\Http\Requests\ViewPricingRequest;
use App\Tenant\Pricing\Http\Resources\VariantPricingResource;
use App\Tenant\Pricing\Models\StoreCurrency;
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
     * Needs the pricing.view permission. Its tax class and whether that is
     * the store's default, its fixed prices, and what one unit costs now in
     * each currency customers can buy in.
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
     * Set a variant's fixed price in a currency.
     *
     * Needs the pricing.manage permission. For one of the store's added
     * currencies (404 otherwise; the base price is the variant's own price), in
     * that currency's smallest unit. It is used as it is instead of converting
     * the base price, and locks the base currency like any price. Audited.
     */
    public function setFixedPrice(SetFixedPriceRequest $request, ProductVariant $variant, StoreCurrency $currency, SetFixedPrice $setFixedPrice, VariantPricingDetails $variantPricingDetails): VariantPricingResource
    {
        $setFixedPrice->handle($variant, $currency, $request->price(), $request->compareAtPrice());

        return new VariantPricingResource($variantPricingDetails->of($variant));
    }

    /**
     * Remove a variant's fixed price in a currency.
     *
     * Needs the pricing.manage permission. Its price in that currency is the
     * converted base price again. Audited.
     */
    public function removeFixedPrice(ManagePricingRequest $request, ProductVariant $variant, StoreCurrency $currency, SetFixedPrice $setFixedPrice, VariantPricingDetails $variantPricingDetails): VariantPricingResource
    {
        $setFixedPrice->remove($variant, $currency);

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
