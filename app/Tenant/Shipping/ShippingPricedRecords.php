<?php

declare(strict_types=1);

namespace App\Tenant\Shipping;

use App\Shared\Money\Contracts\PricedRecords;
use App\Tenant\Shipping\Models\ShippingMethod;

/**
 * Tells Settings whether a shipping method holds amounts in the base currency, which locks the base currency and tax mode (section 3.3).
 *
 * Changing either would silently change what a shipping price means.
 * Archived methods count too: orders still refer to them.
 */
final readonly class ShippingPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return ShippingMethod::query()->exists();
    }
}
