<?php

declare(strict_types=1);

namespace App\Shared\Money;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Stringable;

/**
 * An exact decimal number for internal figures that need more precision than a price, such as exchange rates and bulk unit costs.
 *
 * Never use it for anything a customer pays: those amounts are always Money in
 * whole minor units. Convert a Decimal into Money with Money::fromDecimal(),
 * which rounds half-up. Floats are not accepted anywhere, because they can't
 * represent most decimal fractions exactly.
 */
final readonly class Decimal implements Stringable
{
    private function __construct(private BigDecimal $number) {}

    /**
     * Creates a decimal from a string such as "0.000845" or an integer.
     *
     * @throws InvalidArgumentException When the value is not a valid decimal number.
     */
    public static function of(string|int $value): self
    {
        try {
            return new self(BigDecimal::of($value));
        } catch (MathException $exception) {
            throw new InvalidArgumentException("\"{$value}\" is not a valid decimal number.", 0, $exception);
        }
    }

    /**
     * Adds another number exactly.
     */
    public function add(self|int $other): self
    {
        return new self($this->number->plus(self::toBigDecimal($other)));
    }

    /**
     * Subtracts another number exactly.
     */
    public function subtract(self|int $other): self
    {
        return new self($this->number->minus(self::toBigDecimal($other)));
    }

    /**
     * Multiplies by another number exactly; the result keeps every decimal place.
     */
    public function multiplyBy(self|int $multiplier): self
    {
        return new self($this->number->multipliedBy(self::toBigDecimal($multiplier)));
    }

    /**
     * Divides by another number, rounding half-up to the given number of decimal places.
     *
     * @throws InvalidArgumentException When dividing by zero, or the number of decimal places is negative.
     */
    public function divideBy(self|int $divisor, int $decimalPlaces): self
    {
        $divisorNumber = self::toBigDecimal($divisor);

        if ($divisorNumber->isZero()) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        return new self($this->number->dividedBy($divisorNumber, self::ensureValidDecimalPlaces($decimalPlaces), RoundingMode::HalfUp));
    }

    /**
     * Rounds half-up to the given number of decimal places, for example before storing in a fixed-scale column.
     *
     * @throws InvalidArgumentException When the number of decimal places is negative.
     */
    public function roundTo(int $decimalPlaces): self
    {
        return new self($this->number->toScale(self::ensureValidDecimalPlaces($decimalPlaces), RoundingMode::HalfUp));
    }

    /**
     * The number of decimal places this number currently carries.
     */
    public function decimalPlaces(): int
    {
        return $this->number->getScale();
    }

    /**
     * Whether the two numbers have the same value, ignoring trailing zeros ("1.50" equals "1.5").
     */
    public function isEqualTo(self|int $other): bool
    {
        return $this->number->isEqualTo(self::toBigDecimal($other));
    }

    /**
     * Whether this number is larger than the other.
     */
    public function isGreaterThan(self|int $other): bool
    {
        return $this->number->isGreaterThan(self::toBigDecimal($other));
    }

    /**
     * Whether this number is smaller than the other.
     */
    public function isLessThan(self|int $other): bool
    {
        return $this->number->isLessThan(self::toBigDecimal($other));
    }

    /**
     * Whether this number is exactly zero.
     */
    public function isZero(): bool
    {
        return $this->number->isZero();
    }

    /**
     * Whether this number is below zero.
     */
    public function isNegative(): bool
    {
        return $this->number->isNegative();
    }

    /**
     * The number as a plain decimal string, such as "1575.25", suitable for a decimal database column.
     */
    public function toString(): string
    {
        return $this->number->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return int<0, max>
     *
     * @throws InvalidArgumentException When the number of decimal places is negative.
     */
    private static function ensureValidDecimalPlaces(int $decimalPlaces): int
    {
        if ($decimalPlaces < 0) {
            throw new InvalidArgumentException('The number of decimal places cannot be negative.');
        }

        return $decimalPlaces;
    }

    private static function toBigDecimal(self|int $value): BigDecimal
    {
        return $value instanceof self ? $value->number : BigDecimal::of($value);
    }
}
