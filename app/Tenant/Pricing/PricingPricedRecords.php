<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Shared\Money\Contracts\PricedRecords;
use App\Tenant\Pricing\Models\StoreCurrency;

/**
 * Tells Settings whether Pricing holds anything set in the base currency, which locks it and the tax mode (section 3.3).
 *
 * A manual exchange rate is "units of this currency per unit of the base
 * currency": changing the base currency would silently change its meaning.
 */
final readonly class PricingPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return StoreCurrency::query()->whereNotNull('manual_rate')->exists();
    }
}
