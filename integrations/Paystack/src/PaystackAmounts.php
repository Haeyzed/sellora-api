<?php

declare(strict_types=1);

namespace Integrations\Paystack;

use App\Shared\Money\Money;
use Integrations\Paystack\Exceptions\PaystackAmountException;

/**
 * Money to Paystack's amounts and back. Paystack takes and reports amounts in the smallest unit it uses for each currency, which is its own rule, not always the currency's standard decimals.
 *
 * So each currency is listed with the number of Paystack units in one unit
 * of the currency, and only after a real test-mode payment has confirmed it
 * (CONFIRMED). Any other currency is refused both ways, and so is any amount
 * that would not convert exactly or would not fit: nothing is ever rounded.
 */
final readonly class PaystackAmounts
{
    /**
     * Paystack units in one unit of each currency, confirmed by a real test-mode payment: NGN in kobo, GHS in pesewas, ZAR, KES and USD in cents.
     *
     * @var array<string, int>
     */
    public const array CONFIRMED = [
        'NGN' => 100,
        'GHS' => 100,
        'ZAR' => 100,
        'KES' => 100,
        'USD' => 100,
    ];

    /**
     * @param  array<string, int>  $unitsPerCurrencyUnit  Paystack units in one unit of each currency used.
     */
    public function __construct(private array $unitsPerCurrencyUnit = self::CONFIRMED) {}

    /**
     * What Paystack is sent for an amount: a whole number of its units, above zero.
     *
     * @throws PaystackAmountException
     */
    public function toPaystack(Money $money): int
    {
        $unitsPerCurrencyUnit = $this->unitsPerCurrencyUnit($money->currency());

        if (! $money->isPositive()) {
            throw PaystackAmountException::notPositive($money->currency());
        }

        return self::scale($money->minorAmount(), $unitsPerCurrencyUnit, 10 ** $money->decimalPlaces(), $money->currency());
    }

    /**
     * The Money a Paystack amount stands for, in the currency Paystack reported it in.
     *
     * @throws PaystackAmountException
     */
    public function fromPaystack(int $amount, string $currency): Money
    {
        $currency = mb_strtoupper($currency);
        $unitsPerCurrencyUnit = $this->unitsPerCurrencyUnit($currency);

        if ($amount < 0) {
            throw PaystackAmountException::negative($currency);
        }

        $minorPerCurrencyUnit = 10 ** Money::zero($currency)->decimalPlaces();

        return Money::ofMinor(self::scale($amount, $minorPerCurrencyUnit, $unitsPerCurrencyUnit, $currency), $currency);
    }

    /**
     * @throws PaystackAmountException When Paystack's units for the currency aren't confirmed.
     */
    private function unitsPerCurrencyUnit(string $currency): int
    {
        return $this->unitsPerCurrencyUnit[mb_strtoupper($currency)] ?? throw PaystackAmountException::unsupported($currency);
    }

    /**
     * $amount × $multiplier ÷ $divisor, only when it is a whole number that fits.
     *
     * @throws PaystackAmountException
     */
    private static function scale(int $amount, int $multiplier, int $divisor, string $currency): int
    {
        if ($amount > intdiv(PHP_INT_MAX, $multiplier)) {
            throw PaystackAmountException::tooLarge($currency);
        }

        $scaled = $amount * $multiplier;

        if ($scaled % $divisor !== 0) {
            throw PaystackAmountException::notExact($currency);
        }

        return intdiv($scaled, $divisor);
    }
}
