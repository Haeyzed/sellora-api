<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Data\PricingLine;
use App\Tenant\Pricing\Data\PricingRequest;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Pricing\Exceptions\VariantNotPricedException;
use App\Tenant\Pricing\PriceCalculator;
use App\Tenant\Pricing\VariantPricer;
use App\Tenant\Promotions\Data\DiscountBasket;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Promotions\Data\DiscountLine;
use App\Tenant\Promotions\Data\RedemptionHolder;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Prices lines with the discounts they get: the one calculation Cart shows and Checkout charges (Step 11).
 *
 * Unit prices come from Pricing at the currency's rate; the discounts are
 * worked out on them (ApplyDiscounts) and handed back to Pricing as each
 * line's discount and a lower delivery charge, before tax, so tax is always
 * on what the buyer actually pays.
 */
final readonly class PriceWithDiscounts
{
    public function __construct(
        private FindExchangeRates $findExchangeRates,
        private VariantPricer $variantPricer,
        private ApplyDiscounts $applyDiscounts,
        private PriceCalculator $priceCalculator,
    ) {}

    /**
     * @param  list<array{key: string, variant: ProductVariant, quantity: int}>  $lines
     * @param  Money|null  $shipping  The delivery charge before discounts.
     * @param  string|null  $code  A code already accepted (FindPromotionCode); null for none.
     * @param  RedemptionHolder|null  $holder  The checkout pricing it, whose own reserved uses don't count against it.
     * @param  AppliedExchangeRate|null  $rate  A rate already shown for the currency, such as the one a checkout quote keeps; null for today's.
     *
     * @throws CurrencyNotOfferedException When the store doesn't offer the currency now.
     * @throws VariantNotPricedException When a line's variant has no price.
     * @throws InvalidArgumentException When the rate given is for another currency.
     */
    public function handle(string $currency, array $lines, ?TaxAddress $destination, ?Money $shipping, DiscountBuyer $buyer, ?string $code = null, ?CarbonImmutable $at = null, ?RedemptionHolder $holder = null, ?AppliedExchangeRate $rate = null): DiscountedBasket
    {
        if ($rate !== null && $rate->currency !== $currency) {
            throw new InvalidArgumentException("The rate given is for {$rate->currency}, not {$currency}.");
        }

        $rate ??= $this->findExchangeRates->handle([$currency])[$currency] ?? throw new CurrencyNotOfferedException($currency);
        $prices = $this->variantPricer->priceAt(array_map(static fn (array $line): ProductVariant => $line['variant'], $lines), $rate);
        $discountLines = [];

        foreach ($lines as $line) {
            $price = $prices[$line['variant']->id] ?? throw new VariantNotPricedException($line['variant']->public_id);
            $discountLines[] = new DiscountLine($line['key'], $line['variant'], $line['quantity'], $price->unitPrice, $price->compareAtPrice !== null);
        }

        $outcome = $this->applyDiscounts->handle(new DiscountBasket($currency, $rate, $discountLines, $shipping, $buyer, $code, $at ?? CarbonImmutable::now(), holder: $holder));

        $priced = $this->priceCalculator->quote(new PricingRequest(
            $currency,
            array_map(static fn (array $line): PricingLine => new PricingLine($line['key'], $line['variant'], $line['quantity'], $outcome->lineDiscounts[$line['key']] ?? null), $lines),
            $destination,
            $shipping?->subtract($outcome->shippingDiscount),
            $buyer->customer?->taxCustomer(),
            $rate,
        ));

        return new DiscountedBasket($priced, $outcome);
    }
}
