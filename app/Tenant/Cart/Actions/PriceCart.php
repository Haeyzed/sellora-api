<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

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

/**
 * Works out what a cart comes to now, for the shopper and for Checkout (section 6): never stored, so a cart always shows today's prices, discounts and stock.
 *
 * Lines that can't be bought (no longer for sale, or out of stock without
 * backorders) stay in the cart, marked, and are left out of the totals. The
 * rest are priced through PriceWithDiscounts, the same calculation checkout
 * charges, in the cart's currency or the store's own while that isn't
 * offered. Tax is an estimate, to the country the shopper gave or the
 * store's own, until checkout has an address. Reading changes nothing,
 * however many lines there are, in a fixed number of queries.
 */
final readonly class PriceCart
{
    public function __construct(
        private CartPrices $cartPrices,
        private FindVariantStockStatus $findVariantStockStatus,
        private PriceWithDiscounts $priceWithDiscounts,
    ) {}

    public function handle(Cart $cart): PricedCart
    {
        $cart->loadMissing(['customer', 'lines.variant.product']);
        $currency = $this->cartPrices->currencyOf($cart);
        $statuses = $this->findVariantStockStatus->handle(array_values($cart->lines->map(static fn (CartLine $line): int => $line->product_variant_id)->all()));
        $buyable = $cart->lines->filter(static fn (CartLine $line): bool => self::statusOf($line, $statuses) === CartLineStatus::Available);
        $basket = $buyable->isEmpty() ? null : $this->priceWithDiscounts->handle(
            $currency->code(),
            array_values($buyable->map(static fn (CartLine $line): array => ['key' => $line->public_id, 'variant' => $line->variant, 'quantity' => $line->quantity])->all()),
            self::destinationOf($cart),
            null,
            $cart->customer === null ? DiscountBuyer::guest(null) : DiscountBuyer::customer($cart->customer),
        );

        return new PricedCart(
            $cart,
            $currency,
            array_values($cart->lines->map(static fn (CartLine $line): PricedCartLine => self::lineOf($line, $statuses, $basket))->all()),
            $basket,
            $basket?->priced->destination ?? self::destinationOf($cart),
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

    private static function destinationOf(Cart $cart): ?TaxAddress
    {
        return $cart->destination_country_code === null ? null : new TaxAddress($cart->destination_country_code);
    }
}
