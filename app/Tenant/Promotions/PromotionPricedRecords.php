<?php

declare(strict_types=1);

namespace App\Tenant\Promotions;

use App\Shared\Money\Contracts\PricedRecords;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCurrencyAmount;

/**
 * Tells Settings whether a promotion holds an amount in the base currency (or an exact amount in another), which locks the base currency (section 3.3).
 *
 * Changing the base currency would silently change what "5,000 off" means.
 * Archived promotions count too: orders still refer to them.
 */
final readonly class PromotionPricedRecords implements PricedRecords
{
    public function exist(): bool
    {
        return Promotion::withTrashed()->whereNotNull('currency')->exists() || PromotionCurrencyAmount::query()->exists();
    }
}
