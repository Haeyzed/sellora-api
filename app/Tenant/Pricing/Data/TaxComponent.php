<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Decimal;
use InvalidArgumentException;

/**
 * One tax that applies to a line, before its amount is known, such as "VAT 7.5%" or, from a plug-in, "CGST 9%" and "SGST 9%".
 */
final readonly class TaxComponent
{
    /**
     * @param  string  $name  What it's called on receipts, such as "VAT".
     * @param  string  $countryCode  The country it is owed to, ISO 3166-1 alpha-2.
     * @param  string|null  $stateCode  The state it is owed to, when it is a state's tax.
     * @param  Decimal  $ratePercent  7.5 means 7.5%.
     *
     * @throws InvalidArgumentException When the rate is negative.
     */
    public function __construct(
        public string $name,
        public string $countryCode,
        public ?string $stateCode,
        public Decimal $ratePercent,
    ) {
        if ($ratePercent->isNegative()) {
            throw new InvalidArgumentException('A tax rate cannot be negative.');
        }
    }
}
