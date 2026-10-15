<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Shared\Money\Money;
use App\Tenant\Cart\Data\CartShipping;
use App\Tenant\Cart\Data\PricedCart;
use App\Tenant\Cart\Data\PricedCartLine;
use App\Tenant\Cart\Enums\CartLineStatus;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Models\CartLine;
use App\Tenant\Cart\Services\CartPrices;
use App\Tenant\Cart\Services\CartVariants;
use App\Tenant\Inventory\Actions\FindVariantStockStatus;
use App\Tenant\Inventory\Enums\StockStatus;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Actions\PriceWithDiscounts;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Shipping\Actions\QuoteShipping;
use App\Tenant\Shipping\Data\ShippableLine;
use App\Tenant\Shipping\Data\ShippingQuoteRequest;
use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

/**
 * Works out what a cart comes to now, for the shopper and for Checkout (section 6): never stored, so a cart always shows today's prices, discounts, stock and shipping.
 *
 * Lines that can't be bought (no longer for sale, or out of stock without
 * backorders) stay in the cart, marked, and are left out of the totals. The
 * rest are priced through PriceWithDiscounts, the same calculation checkout
 * charges (automatic promotions and the cart's code), in the cart's currency
 * or the store's own while that isn't offered. Shipping is quoted to the
 * country (and state) the shopper gave, or the store's own, by the weight
 * and value after discounts of the lines that ship; the chosen method's
 * price then joins the basket, so free-shipping promotions and the shipping
 * tax class apply. Tax and shipping are estimates until checkout has an
 * address; a chosen pickup estimates tax at the pickup's address. Reading
 * changes nothing, however many lines there are, in a fixed number of
 * queries.
 */
final readonly class PriceCart
{
    public function __construct(
        private CartPrices $cartPrices,
        private FindVariantStockStatus $findVariantStockStatus,
        private PriceWithDiscounts $priceWithDiscounts,
        private QuoteShipping $quoteShipping,
        private FindStoreSettings $findStoreSettings,
    ) {}

    public function handle(Cart $cart): PricedCart
    {
        $cart->loadMissing(['customer', 'lines.variant.product', 'shippingMethod']);
        $currency = $this->cartPrices->currencyOf($cart);
        $statuses = $this->findVariantStockStatus->handle(array_values($cart->lines->map(static fn (CartLine $line): int => $line->product_variant_id)->all()));
        $buyable = $cart->lines->filter(static fn (CartLine $line): bool => self::statusOf($line, $statuses) === CartLineStatus::Available)->values();
        $chosen = $cart->shippingMethod;
        $storeCountry = $this->findStoreSettings->handle()->country_code;
        $taxDestination = $chosen?->fulfilment === ShippingFulfilment::Pickup && $chosen->pickup_country_code !== null
            ? new TaxAddress($chosen->pickup_country_code, $chosen->pickup_state_code, $chosen->pickup_postal_code)
            : self::destinationOf($cart, $storeCountry);

        // Lines priced with their discounts but no shipping yet: what shipping brackets and thresholds look at.
        $basket = $buyable->isEmpty() ? null : $this->basket($cart, $currency->code(), $buyable, $taxDestination, null);

        $destination = new ShippingRegion($cart->destination_country_code ?? $storeCountry, $cart->destination_state_code);
        $quote = $this->quoteShipping->handle(new ShippingQuoteRequest(
            $currency->rate,
            array_values($buyable->map(static function (CartLine $line) use ($basket): ShippableLine {
                $priced = $basket?->priced->lines[$line->public_id] ?? null;

                return new ShippableLine($line->variant, $line->quantity, $priced === null ? throw new LogicException('Every line that can be bought is priced.') : $priced->subtotal->subtract($priced->discount));
            })->all()),
            $destination,
        ));
        $option = $chosen === null ? null : $quote->option($chosen->public_id);

        if ($basket !== null && $option?->amount !== null) {
            $basket = $this->basket($cart, $currency->code(), $buyable, $taxDestination, $option->amount);
        }

        return new PricedCart(
            $cart,
            $currency,
            array_values($cart->lines->map(static fn (CartLine $line): PricedCartLine => self::lineOf($line, $statuses, $basket))->all()),
            $basket,
            $basket?->priced->destination ?? $taxDestination,
            new CartShipping($quote, $destination, $chosen, $option, $basket?->priced->shipping === null ? null : $basket->discounts->shippingDiscount),
        );
    }

    /**
     * @param  Collection<int, CartLine>  $buyable
     */
    private function basket(Cart $cart, string $currency, Collection $buyable, ?TaxAddress $taxDestination, ?Money $shipping): DiscountedBasket
    {
        return $this->priceWithDiscounts->handle(
            $currency,
            array_values($buyable->map(static fn (CartLine $line): array => ['key' => $line->public_id, 'variant' => $line->variant, 'quantity' => $line->quantity])->all()),
            $taxDestination,
            $shipping,
            $cart->customer === null ? DiscountBuyer::guest(null) : DiscountBuyer::customer($cart->customer),
            $cart->promotion_code,
        );
    }

    /**
     * @param  array<int, StockStatus>  $statuses
     */
    private static function lineOf(CartLine $line, array $statuses, ?DiscountedBasket $basket): PricedCartLine
    {
        $status = self::statusOf($line, $statuses);
        $priced = $basket?->priced->lines[$line->public_id] ?? null;

        return new PricedCartLine(
            $line,
            $status,
            $status === CartLineStatus::Available && $statuses[$line->product_variant_id] === StockStatus::Backorder,
            $priced,
            $priced === null ? null : CartPrices::changeOf($line, $line->variant, $priced->price),
        );
    }

    /**
     * @param  array<int, StockStatus>  $statuses
     */
    private static function statusOf(CartLine $line, array $statuses): CartLineStatus
    {
        return match (true) {
            ! CartVariants::isForSale($line->variant) => CartLineStatus::Unavailable,
            ! $statuses[$line->product_variant_id]->canBeBought() => CartLineStatus::OutOfStock,
            default => CartLineStatus::Available,
        };
    }

    /**
     * Where the shopper said they are; null for the store's own country with no state, which Pricing assumes.
     */
    private static function destinationOf(Cart $cart, string $storeCountry): ?TaxAddress
    {
        return $cart->destination_country_code === null && $cart->destination_state_code === null
            ? null
            : new TaxAddress($cart->destination_country_code ?? $storeCountry, $cart->destination_state_code);
    }
}
