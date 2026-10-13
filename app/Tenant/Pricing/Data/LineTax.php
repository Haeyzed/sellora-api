<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Pricing\Enums\TaxOutcome;
use InvalidArgumentException;

/**
 * A line's tax: its amount before tax (net), the tax, and the two together (gross), which always add up exactly.
 *
 * Calculators build it with atRates() or untaxed(), which hold the rounding
 * rule (section 3.3): per line, half-up, in whole minor units. Exclusive:
 * tax = net × rate / 100. Inclusive: tax = gross × rate / (100 + rate), and
 * net = gross − tax, so the price the customer saw never changes. Several
 * taxes on one line are calculated at their combined rate and split by
 * largest remainder, so the parts add up to the line's tax.
 */
final readonly class LineTax
{
    /**
     * @param  list<AppliedTax>  $taxes  Each tax charged, adding up to $tax.
     *
     * @throws InvalidArgumentException When net and tax don't add up to gross, or the taxes don't add up to the tax.
     */
    public function __construct(
        public string $key,
        public TaxOutcome $outcome,
        public Money $net,
        public Money $tax,
        public Money $gross,
        public array $taxes,
    ) {
        if (! $net->add($tax)->isEqualTo($gross)) {
            throw new InvalidArgumentException("Line \"{$key}\": net and tax must add up to gross.");
        }

        if (! Money::sum($tax->currency(), array_map(static fn (AppliedTax $applied): Money => $applied->amount, $taxes))->isEqualTo($tax)) {
            throw new InvalidArgumentException("Line \"{$key}\": its taxes must add up to its tax.");
        }
    }

    /**
     * A line taxed at the given rates. With no rate above zero, it is zero-rated.
     *
     * @param  non-empty-list<TaxComponent>  $components
     */
    public static function atRates(TaxableLine $line, TaxMode $taxMode, array $components): self
    {
        $combinedRate = array_reduce($components, static fn (Decimal $sum, TaxComponent $component): Decimal => $sum->add($component->ratePercent), Decimal::of(0));
        $currency = $line->amount->currency();

        if ($combinedRate->isZero()) {
            $applied = array_map(static fn (TaxComponent $component): AppliedTax => self::applied($component, Money::zero($currency)), $components);

            return new self($line->key, TaxOutcome::ZeroRated, $line->amount, Money::zero($currency), $line->amount, $applied);
        }

        $divisor = $taxMode === TaxMode::Inclusive ? $combinedRate->add(100) : Decimal::of(100);
        $taxInMinorUnits = Decimal::of($line->amount->minorAmount())->multiplyBy($combinedRate)->divideBy($divisor, 0);
        $tax = Money::ofMinor((int) $taxInMinorUnits->toString(), $currency);

        $parts = $tax->allocate(array_map(static fn (TaxComponent $component): Decimal => $component->ratePercent, $components));
        $applied = array_map(static fn (TaxComponent $component, Money $part): AppliedTax => self::applied($component, $part), $components, $parts);

        return $taxMode === TaxMode::Inclusive
            ? new self($line->key, TaxOutcome::Taxed, $line->amount->subtract($tax), $tax, $line->amount, $applied)
            : new self($line->key, TaxOutcome::Taxed, $line->amount, $tax, $line->amount->add($tax), $applied);
    }

    /**
     * A line with no tax: exempt, or with no rate at its destination.
     */
    public static function untaxed(TaxableLine $line, TaxOutcome $outcome): self
    {
        return new self($line->key, $outcome, $line->amount, Money::zero($line->amount->currency()), $line->amount, []);
    }

    private static function applied(TaxComponent $component, Money $amount): AppliedTax
    {
        return new AppliedTax($component->name, $component->countryCode, $component->stateCode, $component->ratePercent, $amount);
    }
}
