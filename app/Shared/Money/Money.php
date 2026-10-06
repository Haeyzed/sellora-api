<?php

declare(strict_types=1);

namespace App\Shared\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\AllocationMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException as BrickUnknownCurrencyException;
use Brick\Money\Money as BrickMoney;
use InvalidArgumentException;

/**
 * An exact amount of money in one currency, such as a price, a discount, a tax amount or an order total.
 *
 * Amounts are always whole minor units (cents, kobo, yen, fils) of an ISO 4217
 * currency, using that currency's own number of decimal places: USD and NGN
 * have 2, JPY has 0, KWD has 3. Every rounding is half-up, and it happens only
 * here, so all domains round the same way. Domains use this class, never the
 * underlying library, which keeps the library replaceable.
 */
final readonly class Money
{
    private const RoundingMode ROUNDING_MODE = RoundingMode::HalfUp;

    private function __construct(private BrickMoney $amount) {}

    /**
     * Creates an amount from whole minor units, for example 1999 USD cents for $19.99.
     *
     * @throws UnknownCurrencyException When the currency is not an ISO 4217 code.
     */
    public static function ofMinor(int $minorAmount, string $currency): self
    {
        return new self(BrickMoney::ofMinor($minorAmount, self::currencyFor($currency)));
    }

    /**
     * A zero amount in the given currency, the starting point for adding up totals.
     *
     * @throws UnknownCurrencyException When the currency is not an ISO 4217 code.
     */
    public static function zero(string $currency): self
    {
        return new self(BrickMoney::zero(self::currencyFor($currency)));
    }

    /**
     * Turns an internal high-precision figure (such as a bulk unit cost) into money, rounding half-up to the currency's minor unit.
     *
     * @throws UnknownCurrencyException When the currency is not an ISO 4217 code.
     */
    public static function fromDecimal(Decimal $amount, string $currency): self
    {
        return new self(BrickMoney::of(
            BigDecimal::of($amount->toString()),
            self::currencyFor($currency),
            new DefaultContext,
            self::ROUNDING_MODE,
        ));
    }

    /**
     * Adds up a list of amounts, giving zero in the given currency when the list is empty.
     *
     * @param  iterable<self>  $amounts
     *
     * @throws CurrencyMismatchException When any amount is in a different currency.
     */
    public static function sum(string $currency, iterable $amounts): self
    {
        $total = self::zero($currency);

        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }

        return $total;
    }

    /**
     * The amount in whole minor units, for example 1999 for $19.99. This is what is stored and returned by the API.
     */
    public function minorAmount(): int
    {
        return $this->amount->getMinorAmount()->toInt();
    }

    /**
     * The ISO 4217 currency code, for example "USD".
     */
    public function currency(): string
    {
        return $this->amount->getCurrency()->getCurrencyCode();
    }

    /**
     * How many decimal places the currency uses: 2 for USD, 0 for JPY, 3 for KWD.
     */
    public function decimalPlaces(): int
    {
        return $this->amount->getCurrency()->getDefaultFractionDigits();
    }

    /**
     * Adds another amount in the same currency.
     *
     * @throws CurrencyMismatchException When the other amount is in a different currency.
     */
    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amount->plus($other->amount));
    }

    /**
     * Subtracts another amount in the same currency. The result may be negative.
     *
     * @throws CurrencyMismatchException When the other amount is in a different currency.
     */
    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amount->minus($other->amount));
    }

    /**
     * Multiplies the amount, for example a unit price by a quantity, or a price by a tax rate such as 0.075, rounding half-up.
     */
    public function multiplyBy(int|Decimal $multiplier): self
    {
        $exactMultiplier = $multiplier instanceof Decimal ? BigDecimal::of($multiplier->toString()) : $multiplier;

        return new self($this->amount->multipliedBy($exactMultiplier, self::ROUNDING_MODE));
    }

    /**
     * Splits the amount into parts proportional to the given weights, so the parts always add up exactly to the whole.
     *
     * Used to spread an order discount across its lines or a refund across items.
     * Leftover minor units go to the parts that lost the most to rounding
     * (largest remainder), with ties going to the earliest part. Keys are kept.
     *
     * @template TKey of array-key
     *
     * @param  non-empty-array<TKey, int|Decimal>  $weights  Non-negative weights, such as each line's total in minor units.
     * @return array<TKey, self> One part per weight, under the same key.
     *
     * @throws InvalidArgumentException When a weight is negative, or a non-zero amount is split across weights that are all zero.
     */
    public function allocate(array $weights): array
    {
        $ratios = array_map(self::ratioFor(...), $weights);

        if ($this->isZero()) {
            return array_map(fn (string $ratio): self => self::zero($this->currency()), $ratios);
        }

        if (array_all($ratios, static fn (string $ratio): bool => BigDecimal::of($ratio)->isZero())) {
            throw new InvalidArgumentException('Cannot split a non-zero amount across weights that are all zero.');
        }

        $parts = $this->amount->allocate(array_values($ratios), AllocationMode::FloorToLargestRemainder);

        return array_combine(
            array_keys($ratios),
            array_map(static fn (BrickMoney $part): self => new self($part), $parts),
        );
    }

    /**
     * Converts the amount into another currency with the given exchange rate, rounding half-up.
     *
     * The rate is how many units of the target currency one unit of this
     * currency buys (1 USD = 1550.25 NGN). Orders must save the rate they used,
     * so later rate changes never alter past orders.
     *
     * @throws InvalidArgumentException When the rate is not positive, or a currency is converted to itself at a rate other than 1.
     * @throws UnknownCurrencyException When the target currency is not an ISO 4217 code.
     */
    public function convertTo(string $targetCurrency, Decimal $exchangeRate): self
    {
        if (! $exchangeRate->isGreaterThan(0)) {
            throw new InvalidArgumentException('An exchange rate must be greater than zero.');
        }

        if ($targetCurrency === $this->currency() && ! $exchangeRate->isEqualTo(1)) {
            throw new InvalidArgumentException("Converting {$targetCurrency} to itself needs a rate of 1.");
        }

        return new self(BrickMoney::of(
            $this->amount->getAmount()->multipliedBy(BigDecimal::of($exchangeRate->toString())),
            self::currencyFor($targetCurrency),
            new DefaultContext,
            self::ROUNDING_MODE,
        ));
    }

    /**
     * The same amount with the opposite sign, for example to record a refund as a negative adjustment.
     */
    public function negate(): self
    {
        return new self($this->amount->negated());
    }

    /**
     * Whether both amounts are the same value in the same currency. Amounts in different currencies are never equal.
     */
    public function isEqualTo(self $other): bool
    {
        return $this->hasSameCurrencyAs($other) && $this->amount->isEqualTo($other->amount);
    }

    /**
     * Whether both amounts are in the same currency.
     */
    public function hasSameCurrencyAs(self $other): bool
    {
        return $this->amount->getCurrency()->isEqualTo($other->amount->getCurrency());
    }

    /**
     * Whether this amount is larger than the other.
     *
     * @throws CurrencyMismatchException When the other amount is in a different currency.
     */
    public function isGreaterThan(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->amount->isGreaterThan($other->amount);
    }

    /**
     * Whether this amount is smaller than the other.
     *
     * @throws CurrencyMismatchException When the other amount is in a different currency.
     */
    public function isLessThan(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->amount->isLessThan($other->amount);
    }

    /**
     * Whether the amount is exactly zero.
     */
    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    /**
     * Whether the amount is above zero.
     */
    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    /**
     * Whether the amount is below zero, such as a refund or a credit.
     */
    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    /**
     * The amount as people read it in the given locale, for example "$1,250.00" in "en" or "1.250,00 €" in "de".
     *
     * For display only; never parse it back into an amount.
     */
    public function format(string $locale): string
    {
        return $this->amount->formatToLocale($locale);
    }

    private function ensureSameCurrency(self $other): void
    {
        if (! $this->hasSameCurrencyAs($other)) {
            throw CurrencyMismatchException::between($this->currency(), $other->currency());
        }
    }

    private static function currencyFor(string $currency): Currency
    {
        try {
            return Currency::of($currency);
        } catch (BrickUnknownCurrencyException) {
            throw UnknownCurrencyException::forCode($currency);
        }
    }

    /**
     * @throws InvalidArgumentException When the weight is negative.
     */
    private static function ratioFor(int|Decimal $weight): string
    {
        $ratio = $weight instanceof Decimal ? $weight->toString() : (string) $weight;

        if (BigDecimal::of($ratio)->isNegative()) {
            throw new InvalidArgumentException('Weights used to split an amount cannot be negative.');
        }

        return $ratio;
    }
}
