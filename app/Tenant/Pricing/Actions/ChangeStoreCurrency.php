<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Shared\Money\Decimal;
use App\Tenant\Pricing\Models\StoreCurrency;
use Carbon\CarbonImmutable;

/**
 * Changes one of the store's currencies: whether customers can buy in it, its margin on the market rate, or its manual rate. Audited.
 *
 * Deactivating never deletes anything; reactivating counts towards the
 * plan's limit again. A store over its limit after a downgrade keeps the
 * currencies it has, it only can't activate more.
 */
final readonly class ChangeStoreCurrency
{
    public function __construct(private Features $features) {}

    /**
     * @param  Decimal|null  $marginPercent  From 0 to 25, already validated; null leaves it.
     *
     * @throws UsageLimitReachedException When reactivating it would go beyond the plan's limit.
     */
    public function handle(StoreCurrency $currency, ?bool $isActive, ?Decimal $marginPercent): StoreCurrency
    {
        if ($isActive === true && ! $currency->is_active) {
            $this->features->ensureWithinLimit(AddStoreCurrency::LIMIT_KEY, StoreCurrency::query()->active()->count());
        }

        if ($isActive !== null) {
            $currency->is_active = $isActive;
        }

        if ($marginPercent !== null) {
            $currency->margin_percent = $marginPercent;
        }

        $currency->save();

        return $currency;
    }

    /**
     * Sets the rate prices are converted at instead of the market rate, or with null goes back to the market rate.
     *
     * @param  Decimal|null  $rate  Units of this currency per unit of the base currency, above zero, already validated.
     */
    public function setManualRate(StoreCurrency $currency, ?Decimal $rate): StoreCurrency
    {
        $currency->manual_rate = $rate;
        $currency->manual_rate_set_at = $rate === null ? null : CarbonImmutable::now();
        $currency->save();

        return $currency;
    }
}
