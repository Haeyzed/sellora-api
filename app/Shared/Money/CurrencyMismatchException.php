<?php

declare(strict_types=1);

namespace App\Shared\Money;

use LogicException;

/**
 * Raised when code tries to combine amounts in different currencies, such as adding USD to EUR.
 *
 * This is always a programming mistake, never a customer error: amounts must be
 * converted with an exchange rate first. It is not a business rule, so it is
 * reported as a bug instead of being shown to the customer.
 */
final class CurrencyMismatchException extends LogicException
{
    public static function between(string $expectedCurrency, string $actualCurrency): self
    {
        return new self("Expected an amount in {$expectedCurrency}, got {$actualCurrency}.");
    }
}
