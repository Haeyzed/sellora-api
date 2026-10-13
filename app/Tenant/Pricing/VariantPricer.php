<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Data\VariantPrice;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Pricing\Models\FixedPrice;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * What variants cost in a currency, for a whole list at once in a fixed number of queries: catalog pages, carts and checkouts (section 3.3).
 *
 * A fixed price for the currency is used as it is. Otherwise the base price
 * is converted at the store's rate, per unit, rounded half-up to the
 * currency's ISO 4217 decimals; a converted compare-at price that is no longer
 * above the price is dropped. Unpriced variants are left out: they can't be
 * bought.
 */
final readonly class VariantPricer
{
    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private FindExchangeRates $findExchangeRates,
    ) {}

    /**
     * Prices in a currency the store offers now: five queries at most, however many variants.
     *
     * @param  iterable<ProductVariant>  $variants
     * @return array<int, VariantPrice> By variant ID; unpriced variants are left out.
     *
     * @throws CurrencyNotOfferedException When the store doesn't offer the currency now.
     */
    public function priceIn(iterable $variants, string $currency): array
    {
        $rate = $this->findExchangeRates->handle([$currency])[$currency] ?? throw new CurrencyNotOfferedException($currency);

        return $this->priceAt($variants, $rate);
    }

    /**
     * Prices at a rate already found, such as one a checkout keeps: two queries at most.
     *
     * @param  iterable<ProductVariant>  $variants
     * @return array<int, VariantPrice> By variant ID; unpriced variants are left out.
     */
    public function priceAt(iterable $variants, AppliedExchangeRate $rate): array
    {
        $priced = [];

        foreach ($variants as $variant) {
            if ($variant->price !== null) {
                $priced[$variant->id] = [$variant->price, $variant->compare_at_price];
            }
        }

        if ($priced === []) {
            return [];
        }

        $includesTax = $this->findStoreSettings->handle()->tax_mode === TaxMode::Inclusive;
        $fixedPrices = $rate->source === ExchangeRateSource::Base ? [] : FixedPrice::query()
            ->whereIn('product_variant_id', array_keys($priced))
            ->where('currency_code', $rate->currency)
            ->get()
            ->keyBy('product_variant_id')
            ->all();
        $prices = [];

        foreach ($priced as $variantId => [$basePrice, $baseCompareAtPrice]) {
            $fixed = $fixedPrices[$variantId] ?? null;

            $prices[$variantId] = $fixed === null
                ? $this->converted($variantId, $basePrice, $baseCompareAtPrice, $rate, $includesTax)
                : new VariantPrice($variantId, $fixed->price, $fixed->compare_at_price, true, $includesTax, $rate);
        }

        return $prices;
    }

    private function converted(int $variantId, Money $basePrice, ?Money $baseCompareAtPrice, AppliedExchangeRate $rate, bool $includesTax): VariantPrice
    {
        $price = $this->convert($basePrice, $rate);
        $compareAtPrice = $baseCompareAtPrice === null ? null : $this->convert($baseCompareAtPrice, $rate);

        return new VariantPrice(
            $variantId,
            $price,
            $compareAtPrice !== null && $compareAtPrice->isGreaterThan($price) ? $compareAtPrice : null,
            false,
            $includesTax,
            $rate,
        );
    }

    private function convert(Money $amount, AppliedExchangeRate $rate): Money
    {
        return $rate->source === ExchangeRateSource::Base ? $amount : $amount->convertTo($rate->currency, $rate->rate);
    }
}
