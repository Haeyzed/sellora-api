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
use App\Tenant\Promotions\Data\DiscountOutcome;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Data\PriceAllowances;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Enums\DiscountClass;
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
     * @param  PriceAllowances|null  $allowances  A replacement order's fixed reductions (Step 18c), taken off after promotions; only replacement orders give them.
     *
     * @throws CurrencyNotOfferedException When the store doesn't offer the currency now.
     * @throws VariantNotPricedException When a line's variant has no price.
     * @throws InvalidArgumentException When the rate given is for another currency, or an allowance doesn't fit the lines.
     */
    public function handle(string $currency, array $lines, ?TaxAddress $destination, ?Money $shipping, DiscountBuyer $buyer, ?string $code = null, ?CarbonImmutable $at = null, ?RedemptionHolder $holder = null, ?AppliedExchangeRate $rate = null, ?PriceAllowances $allowances = null): DiscountedBasket
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

        if ($allowances !== null) {
            $outcome = $this->withAllowances($outcome, $discountLines, $shipping, $allowances);
        }

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

    /**
     * The promotions' outcome with the allowances taken off what each line and the delivery still cost, never more, each recorded as a discount of its own.
     *
     * @param  list<DiscountLine>  $discountLines
     *
     * @throws InvalidArgumentException When an allowance is for no line, in another currency, or negative.
     */
    private function withAllowances(DiscountOutcome $outcome, array $discountLines, ?Money $shipping, PriceAllowances $allowances): DiscountOutcome
    {
        $currency = $outcome->currency;
        $lineDiscounts = $outcome->lineDiscounts;
        $byKey = [];

        foreach ($discountLines as $line) {
            $byKey[$line->key] = $line;
        }

        $taken = [];

        foreach ($allowances->lines as $key => $allowance) {
            $line = $byKey[$key] ?? throw new InvalidArgumentException("The allowance is for line \"{$key}\", which isn't priced.");
            $already = $lineDiscounts[$key] ?? Money::zero($currency);
            $amount = self::capped($allowance, $line->subtotal()->subtract($already), $currency);

            if ($amount->isPositive()) {
                $taken[$key] = $amount;
                $lineDiscounts[$key] = $already->add($amount);
            }
        }

        $shippingTaken = $allowances->shipping === null || $shipping === null
            ? Money::zero($currency)
            : self::capped($allowances->shipping, $shipping->subtract($outcome->shippingDiscount), $currency);

        $applied = $outcome->applied;

        if ($taken !== []) {
            $applied[] = self::proposal($allowances, DiscountClass::Product, $taken, null);
        }

        if ($shippingTaken->isPositive()) {
            $applied[] = self::proposal($allowances, DiscountClass::Shipping, [], $shippingTaken);
        }

        return new DiscountOutcome($currency, $applied, $lineDiscounts, $outcome->shippingDiscount->add($shippingTaken), $outcome->code, $outcome->codeReason);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function capped(Money $allowance, Money $left, string $currency): Money
    {
        if ($allowance->currency() !== $currency || $allowance->isNegative()) {
            throw new InvalidArgumentException("An allowance must be a positive amount in {$currency}.");
        }

        return $allowance->isGreaterThan($left) ? $left : $allowance;
    }

    /**
     * @param  array<string, Money>  $lineDiscounts
     */
    private static function proposal(PriceAllowances $allowances, DiscountClass $class, array $lineDiscounts, ?Money $shippingDiscount): DiscountProposal
    {
        return new DiscountProposal(
            PriceAllowances::SOURCE,
            $allowances->reference,
            PHP_INT_MAX,
            $allowances->label,
            $class,
            DiscountClass::cases(),
            $lineDiscounts,
            $shippingDiscount,
            false,
            // Fixed amounts the exchange agreed: never worked out again for another basket.
            static fn (): ?DiscountProposal => null,
        );
    }
}
