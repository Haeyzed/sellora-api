<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Actions\PriceWithDiscounts;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Shipping\Data\CarrierAddress;
use App\Tenant\Shipping\Data\ShippableLine;
use App\Tenant\Shipping\Data\ShippedBasket;
use App\Tenant\Shipping\Data\ShippingQuoteRequest;
use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Models\ShippingMethod;
use LogicException;

/**
 * Prices lines together with their delivery, the one way a cart, a checkout and a replacement order all do it (section 6): the lines with their promotions first, then shipping quoted by their weight and value after those discounts, then the lines again with the chosen way's price, so free-shipping promotions and the shipping tax class apply.
 *
 * Never stored. Takes no allowances: a replacement order takes its own off
 * afterwards, through PriceWithDiscounts.
 */
final readonly class PriceBasketWithShipping
{
    public function __construct(
        private PriceWithDiscounts $priceWithDiscounts,
        private QuoteShipping $quoteShipping,
    ) {}

    /**
     * @param  list<array{key: string, variant: ProductVariant, quantity: int}>  $lines  The lines that can be bought.
     * @param  TaxAddress|null  $taxDestination  Where tax is worked out to; null for the store's own country.
     * @param  ShippingRegion  $destination  Where it would be sent: a country, and its state when known.
     * @param  CarrierAddress|null  $address  The full address, once known: carrier methods are quoted live with it.
     * @param  ShippingMethod|null  $chosen  The way to ship chosen; null for none yet.
     * @param  string|null  $code  A promotion code already accepted; null for none.
     * @param  RedemptionHolder|null  $holder  What is pricing it, whose own reserved promotion uses don't count against it.
     */
    public function handle(
        AppliedExchangeRate $rate,
        array $lines,
        ?TaxAddress $taxDestination,
        ShippingRegion $destination,
        ?CarrierAddress $address,
        ?ShippingMethod $chosen,
        DiscountBuyer $buyer,
        ?string $code = null,
        ?RedemptionHolder $holder = null,
    ): ShippedBasket {
        // Lines priced with their discounts but no delivery yet: what shipping brackets and thresholds look at.
        $basket = $lines === [] ? null : $this->price($rate, $lines, $taxDestination, null, $buyer, $code, $holder);

        $quote = $this->quoteShipping->handle(new ShippingQuoteRequest(
            $rate,
            array_map(static function (array $line) use ($basket): ShippableLine {
                $priced = $basket?->priced->lines[$line['key']] ?? throw new LogicException('Every line that can be bought is priced.');

                return new ShippableLine($line['variant'], $line['quantity'], $priced->subtotal->subtract($priced->discount));
            }, $lines),
            $destination,
            $address,
        ));
        $option = $chosen === null ? null : $quote->option($chosen->public_id);

        if ($basket !== null && $option?->amount !== null) {
            $basket = $this->price($rate, $lines, $taxDestination, $option->amount, $buyer, $code, $holder);
        }

        return new ShippedBasket($basket, $quote, $option);
    }

    /**
     * @param  list<array{key: string, variant: ProductVariant, quantity: int}>  $lines
     */
    private function price(AppliedExchangeRate $rate, array $lines, ?TaxAddress $taxDestination, ?Money $shipping, DiscountBuyer $buyer, ?string $code, ?RedemptionHolder $holder): DiscountedBasket
    {
        return $this->priceWithDiscounts->handle($rate->currency, $lines, $taxDestination, $shipping, $buyer, $code, holder: $holder, rate: $rate);
    }
}
