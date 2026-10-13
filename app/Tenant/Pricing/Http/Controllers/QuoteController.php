<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Pricing\Exceptions\DiscountExceedsLineException;
use App\Tenant\Pricing\Exceptions\VariantNotPricedException;
use App\Tenant\Pricing\Http\Requests\QuotePricesRequest;
use App\Tenant\Pricing\Http\Resources\PricedBasketResource;
use App\Tenant\Pricing\PriceCalculator;

/**
 * Previews of what lines cost with tax, for staff checking their prices, currencies and tax rates.
 */
final class QuoteController extends Controller
{
    /**
     * Preview prices and tax.
     *
     * Needs the pricing.view permission. Prices the lines exactly as a cart
     * and checkout will: in a currency the store offers (its own when left
     * out), to a destination (its own country when left out), with any line
     * discounts and a delivery charge. Lines without a tax rate at the
     * destination are marked "no_rate". Nothing is saved.
     *
     * @throws CurrencyNotOfferedException
     * @throws DiscountExceedsLineException
     * @throws VariantNotPricedException
     */
    public function __invoke(QuotePricesRequest $request, PriceCalculator $priceCalculator): PricedBasketResource
    {
        return new PricedBasketResource($priceCalculator->quote($request->pricingRequest()));
    }
}
