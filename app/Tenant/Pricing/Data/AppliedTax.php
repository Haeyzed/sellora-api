<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;

/**
 * One tax charged on a line, with its amount: what orders save as the tax actually applied, never recalculated (section 3.3).
 */
final readonly class AppliedTax
{
    /**
     * @param  Decimal  $ratePercent  7.5 means 7.5%.
     */
    public function __construct(
        public string $name,
        public string $countryCode,
        public ?string $stateCode,
        public Decimal $ratePercent,
        public Money $amount,
    ) {}
}
