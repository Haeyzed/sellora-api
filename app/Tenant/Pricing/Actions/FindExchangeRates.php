<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Pricing\Services\StoreCurrencyRates;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * The rates the store converts its base prices at, for the currencies it offers now: the one way other domains get a store's rate (section 3.3).
 *
 * The base currency is always offered, at 1. Another currency is offered
 * while it is active and has a manual rate or a market rate younger than the
 * stale limit; others are left out of the answer.
 */
final readonly class FindExchangeRates
{
    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private StoreCurrencyRates $storeCurrencyRates,
    ) {}

    /**
     * @param  list<string>  $currencies  ISO 4217 codes.
     * @return array<string, AppliedExchangeRate> By code, only the offered ones.
     */
    public function handle(array $currencies): array
    {
        $baseCurrency = $this->findStoreSettings->handle()->currency_code;
        $rates = in_array($baseCurrency, $currencies, true) ? [$baseCurrency => AppliedExchangeRate::base($baseCurrency)] : [];
        $others = array_values(array_diff($currencies, [$baseCurrency]));

        if ($others === []) {
            return $rates;
        }

        foreach ($this->storeCurrencyRates->statusesOf($baseCurrency, StoreCurrency::query()->whereIn('currency_code', $others)->get()) as $code => $status) {
            if ($status->isOffered() && $status->appliedRate !== null) {
                $rates[$code] = $status->appliedRate;
            }
        }

        return $rates;
    }

    /**
     * The rate of every currency customers can buy in now: the base currency first, then the others by code.
     *
     * @return non-empty-list<AppliedExchangeRate>
     */
    public function offered(): array
    {
        $baseCurrency = $this->findStoreSettings->handle()->currency_code;
        $statuses = $this->storeCurrencyRates->statusesOf($baseCurrency, StoreCurrency::query()->active()->where('currency_code', '<>', $baseCurrency)->orderBy('currency_code')->get());
        $offered = [AppliedExchangeRate::base($baseCurrency)];

        foreach ($statuses as $status) {
            if ($status->isOffered() && $status->appliedRate !== null) {
                $offered[] = $status->appliedRate;
            }
        }

        return $offered;
    }
}
