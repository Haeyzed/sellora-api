<?php

declare(strict_types=1);

namespace App\Tenant\Customers;

/**
 * The format check for tax IDs customers enter (Step 10 decision: format only, never verified with a registry).
 *
 * Formats differ too much between countries to guess them one by one, so
 * any ID of 4 to 20 letters and digits passes, once spaces, dots, dashes and
 * slashes are taken out and letters are upper-cased: "GB 123 456 789",
 * "12345678-0001" and "DE123456789" all do. Staff check the ID itself before
 * making the customer tax-exempt.
 */
final class TaxIdFormat
{
    public static function normalise(string $taxId): string
    {
        return mb_strtoupper((string) preg_replace('/[\s.\-\/]+/u', '', $taxId));
    }

    public static function isValid(string $taxId): bool
    {
        return preg_match('/^[A-Z0-9]{4,20}$/', self::normalise($taxId)) === 1;
    }
}
