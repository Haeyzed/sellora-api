<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates;

use App\Shared\Money\ExchangeRates\Models\ExchangeRate;
use Carbon\CarbonImmutable;

/**
 * Read-only market exchange rates, by ISO currency code: the only way stores read the platform's rates (section 2.1).
 *
 * Rates are kept against USD; a store's rate between its base currency and
 * another is the cross rate of the two latest ones. One query, however many
 * currencies.
 */
final readonly class ExchangeRates
{
    /** Decimal places of a cross rate, so even a weak base currency against a strong one keeps its precision. */
    public const int CROSS_RATE_SCALE = 18;

    /**
     * The market rate from the base currency to each quote currency, as of the latest fetch of both. A currency with no rate yet is left out.
     *
     * @param  list<string>  $quoteCurrencies
     * @return array<string, CrossRate> By quote currency.
     */
    public function crossRates(string $baseCurrency, array $quoteCurrencies): array
    {
        if ($quoteCurrencies === []) {
            return [];
        }

        $latest = $this->latest([$baseCurrency, ...$quoteCurrencies]);
        $base = $latest[$baseCurrency] ?? null;

        if ($base === null) {
            return [];
        }

        $crossRates = [];

        foreach ($quoteCurrencies as $quoteCurrency) {
            $quote = $latest[$quoteCurrency] ?? null;

            if ($quote !== null) {
                $crossRates[$quoteCurrency] = new CrossRate(
                    $baseCurrency,
                    $quoteCurrency,
                    $quote->units_per_usd->divideBy($base->units_per_usd, self::CROSS_RATE_SCALE),
                    $quote->fetched_at->min($base->fetched_at),
                );
            }
        }

        return $crossRates;
    }

    /**
     * The market rate from one currency to another, or null when either has no rate yet.
     */
    public function crossRate(string $baseCurrency, string $quoteCurrency): ?CrossRate
    {
        return $this->crossRates($baseCurrency, [$quoteCurrency])[$quoteCurrency] ?? null;
    }

    /**
     * When rates were last fetched successfully, or null if never.
     */
    public function lastFetchedAt(): ?CarbonImmutable
    {
        $fetchedAt = ExchangeRate::query()->max('fetched_at');

        return $fetchedAt === null ? null : CarbonImmutable::parse((string) $fetchedAt);
    }

    /**
     * Each currency's latest rate.
     *
     * @param  list<string>  $currencies
     * @return array<string, ExchangeRate>
     */
    private function latest(array $currencies): array
    {
        return ExchangeRate::query()
            ->selectRaw('distinct on (currency) *')
            ->whereIn('currency', array_values(array_unique($currencies)))
            ->orderBy('currency')
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->get()
            ->keyBy('currency')
            ->all();
    }
}
