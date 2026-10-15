<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Services;

use App\Shared\Money\Money;
use App\Tenant\Cart\Data\CartCurrency;
use App\Tenant\Cart\Data\PriceChange;
use App\Tenant\Cart\Enums\SeenPriceSource;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Models\CartLine;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Data\VariantPrice;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use App\Tenant\Pricing\VariantPricer;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * The prices a cart shows: which currency it is in, and whether each line's price moved since the shopper saw it.
 *
 * A line remembers the price the shopper last saw. A fixed price is
 * remembered in its own currency and compared in it; any other price is
 * remembered as the base price, so a change in exchange rates is never
 * mistaken for the merchant changing a price. When the two can't be compared
 * (the shopper switched currency, or the merchant set or removed a fixed
 * price) nothing is flagged.
 */
final readonly class CartPrices
{
    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private FindExchangeRates $findExchangeRates,
        private VariantPricer $variantPricer,
    ) {}

    public function currencyOf(Cart $cart): CartCurrency
    {
        $baseCurrency = $this->findStoreSettings->handle()->currency_code;

        if ($cart->currency === null || $cart->currency === $baseCurrency) {
            return new CartCurrency(AppliedExchangeRate::base($baseCurrency), null);
        }

        $rate = $this->findExchangeRates->handle([$cart->currency])[$cart->currency] ?? null;

        return $rate === null ? new CartCurrency(AppliedExchangeRate::base($baseCurrency), $cart->currency) : new CartCurrency($rate, null);
    }

    /**
     * What one unit of the variant costs in the cart now; null when it has no price.
     */
    public function unitPriceOf(Cart $cart, ProductVariant $variant): ?VariantPrice
    {
        return $this->variantPricer->priceAt([$variant], $this->currencyOf($cart)->rate)[$variant->id] ?? null;
    }

    /**
     * Remembers every priced line's price as the shopper sees it now, in the cart's currency as it stands.
     */
    public function rememberAll(Cart $cart): void
    {
        $lines = $cart->lines()->with('variant')->get();
        $prices = $this->variantPricer->priceAt($lines->map(static fn (CartLine $line): ProductVariant => $line->variant), $this->currencyOf($cart)->rate);

        foreach ($lines as $line) {
            $price = $prices[$line->product_variant_id] ?? null;

            if ($price !== null) {
                self::remember($line, $line->variant, $price);
                $line->save();
            }
        }
    }

    /**
     * Remembers the price as the shopper sees it now.
     */
    public static function remember(CartLine $line, ProductVariant $variant, VariantPrice $price): void
    {
        if ($price->isFixed || $variant->price === null) {
            $line->fill(['seen_price' => $price->unitPrice, 'seen_price_source' => SeenPriceSource::Fixed]);

            return;
        }

        $line->fill(['seen_price' => $variant->price, 'seen_price_source' => SeenPriceSource::Converted]);
    }

    /**
     * How the line's price moved since the shopper saw it; null when it didn't, or when the two can't be compared.
     */
    public static function changeOf(CartLine $line, ProductVariant $variant, VariantPrice $price): ?PriceChange
    {
        $seen = $line->seen_price;

        if ($line->seen_price_source === SeenPriceSource::Fixed) {
            return $price->isFixed && $seen->currency() === $price->unitPrice->currency() && ! $seen->isEqualTo($price->unitPrice)
                ? new PriceChange($seen, $price->unitPrice)
                : null;
        }

        if ($price->isFixed || $variant->price === null || $seen->currency() !== $variant->price->currency() || $seen->isEqualTo($variant->price)) {
            return null;
        }

        $was = self::converted($seen, $price->exchangeRate);

        return $was->isEqualTo($price->unitPrice) ? null : new PriceChange($was, $price->unitPrice);
    }

    /**
     * A base price in the cart's currency, as VariantPricer converts it.
     */
    private static function converted(Money $basePrice, AppliedExchangeRate $rate): Money
    {
        return $rate->source === ExchangeRateSource::Base ? $basePrice : $basePrice->convertTo($rate->currency, $rate->rate);
    }
}
