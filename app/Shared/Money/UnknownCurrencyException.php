<?php

declare(strict_types=1);

namespace App\Shared\Money;

use InvalidArgumentException;

/**
 * Raised when a currency code is not an ISO 4217 currency, such as "XYZ" or "usd ".
 *
 * Currency codes from customers or merchants must be validated in a Form
 * Request first; reaching this exception means unvalidated input got through.
 */
final class UnknownCurrencyException extends InvalidArgumentException
{
    public static function forCode(string $currency): self
    {
        return new self("\"{$currency}\" is not an ISO 4217 currency code.");
    }
}
