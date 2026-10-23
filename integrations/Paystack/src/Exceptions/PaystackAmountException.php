<?php

declare(strict_types=1);

namespace Integrations\Paystack\Exceptions;

use InvalidArgumentException;

/**
 * An amount that can't go to or come from Paystack exactly: a currency whose Paystack units aren't confirmed, or an amount that is not positive, not whole in Paystack's units, or too large. Never rounded around: a bug or a gateway answer to look at.
 */
final class PaystackAmountException extends InvalidArgumentException
{
    public static function unsupported(string $currency): self
    {
        return new self("Paystack's units for {$currency} are not confirmed, so amounts in it are refused.");
    }

    public static function notPositive(string $currency): self
    {
        return new self("A {$currency} amount sent to Paystack must be above zero.");
    }

    public static function negative(string $currency): self
    {
        return new self("Paystack reported a negative {$currency} amount.");
    }

    public static function notExact(string $currency): self
    {
        return new self("A {$currency} amount does not convert to or from Paystack's units exactly.");
    }

    public static function tooLarge(string $currency): self
    {
        return new self("A {$currency} amount is too large to convert to or from Paystack's units.");
    }
}
