<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\ExchangeRates\CrossRate;
use App\Tenant\Pricing\Models\StoreCurrency;

/**
 * Where one of the store's currencies stands: its market rate, the rate prices use, and whether customers can buy in it now.
 */
final readonly class CurrencyStatus
{
    /**
     * @param  CrossRate|null  $marketRate  The latest market rate from the base currency; null when the platform has none for it.
     * @param  AppliedExchangeRate|null  $appliedRate  The rate prices use; null when it can't be offered.
     * @param  bool  $isStale  True when its market rate is older than the limit and no manual rate stands in.
     */
    public function __construct(
        public StoreCurrency $currency,
        public ?CrossRate $marketRate,
        public ?AppliedExchangeRate $appliedRate,
        public bool $isStale,
    ) {}

    /**
     * Whether customers can be shown prices and buy in it now: active, with a manual rate or a fresh market rate.
     */
    public function isOffered(): bool
    {
        return $this->currency->is_active && $this->appliedRate !== null;
    }
}
