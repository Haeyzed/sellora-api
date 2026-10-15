<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Pricing\Exceptions\VariantNotPricedException;
use App\Tenant\Pricing\VariantPricer;
use App\Tenant\Shipping\Actions\QuoteShipping;
use App\Tenant\Shipping\Data\ShippableLine;
use App\Tenant\Shipping\Data\ShippingQuoteRequest;
use App\Tenant\Shipping\Http\Requests\QuoteShippingRequest;
use App\Tenant\Shipping\Http\Resources\ShippingQuoteResource;

/**
 * Previews of what shipping costs, for staff checking their zones, methods and rates.
 */
final class ShippingQuoteController extends Controller
{
    /**
     * Preview shipping.
     *
     * Needs the shipping.view permission. Lists the methods a basket of
     * these lines would be offered to the destination, and what each costs,
     * exactly as a cart will: by the weight and value of the lines that ship,
     * in a currency the store offers (its own when left out). Lines are
     * valued at their prices, before any discount. Nothing is saved.
     *
     * @throws CurrencyNotOfferedException
     * @throws VariantNotPricedException
     */
    public function __invoke(QuoteShippingRequest $request, FindExchangeRates $findExchangeRates, VariantPricer $variantPricer, QuoteShipping $quoteShipping): ShippingQuoteResource
    {
        $currency = $request->currency();
        $exchangeRate = $findExchangeRates->handle([$currency])[$currency] ?? throw new CurrencyNotOfferedException($currency);
        $lines = $request->lines();
        $prices = $variantPricer->priceAt(array_column($lines, 'variant'), $exchangeRate);

        $shippable = array_map(static function (array $line) use ($prices): ShippableLine {
            $price = $prices[$line['variant']->id] ?? throw new VariantNotPricedException($line['variant']->sku ?? $line['variant']->public_id);

            return new ShippableLine($line['variant'], $line['quantity'], $price->unitPrice->multiplyBy($line['quantity']));
        }, $lines);

        return new ShippingQuoteResource($quoteShipping->handle(new ShippingQuoteRequest($exchangeRate, $shippable, $request->destination())));
    }
}
