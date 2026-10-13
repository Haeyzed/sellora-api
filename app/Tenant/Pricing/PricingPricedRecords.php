<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Shared\Money\Contracts\PricedRecords;
use App\Tenant\Pricing\Models\FixedPrice;
use App\Tenant\Pricing\Models\StoreCurrency;

/**
 * Tells Settings whether Pricing holds anything set in the base currency, which locks it and the tax mode (section 3.3).
 *
 * A fixed price in another currency, or a manual exchange rate ("units of
 * this currency per unit of the base currency"): changing the base currency
 * or tax mode would silently change what they mean.
 */
final readonly class PricingPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return FixedPrice::query()->exists() || StoreCurrency::query()->whereNotNull('manual_rate')->exists();
    }
}
