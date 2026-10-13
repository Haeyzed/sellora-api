<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Services;

use App\Shared\Money\Decimal;
use App\Shared\Money\ExchangeRates\CrossRate;
use App\Shared\Money\ExchangeRates\ExchangeRates;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Data\CurrencyStatus;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use App\Tenant\Pricing\Models\StoreCurrency;
use Carbon\CarbonImmutable;

/**
 * Works out the rate each of the store's currencies converts at, and whether it can be offered (section 3.3).
 *
 * A manual rate is used as it is. Otherwise the market cross rate from the
 * base currency is used, plus the store's margin, unless it is older than
 * the stale limit (48 hours): then the currency isn't offered, so no price is
 * ever quoted on an old rate. One central query, however many currencies.
 */
final readonly class StoreCurrencyRates
{
    /** Decimal places of an applied rate, as of the market cross rate. */
    private const int RATE_SCALE = ExchangeRates::CROSS_RATE_SCALE;

    public function __construct(private ExchangeRates $exchangeRates) {}

    /**
     * @param  iterable<StoreCurrency>  $currencies
     * @return array<string, CurrencyStatus> By currency code.
     */
    public function statusesOf(string $baseCurrency, iterable $currencies): array
    {
        $byCode = [];

        foreach ($currencies as $currency) {
            $byCode[$currency->currency_code] = $currency;
        }

        $marketRates = $this->exchangeRates->crossRates($baseCurrency, array_keys($byCode));
        $staleBefore = CarbonImmutable::now()->subHours(config()->integer('exchange_rates.stale_after_hours'));
        $statuses = [];

        foreach ($byCode as $code => $currency) {
            $market = $marketRates[$code] ?? null;
            $isStale = $currency->manual_rate === null && $market !== null && $market->asOf->lessThan($staleBefore);

            $statuses[$code] = new CurrencyStatus($currency, $market, $isStale ? null : $this->applied($baseCurrency, $currency, $market), $isStale);
        }

        return $statuses;
    }

    private function applied(string $baseCurrency, StoreCurrency $currency, ?CrossRate $market): ?AppliedExchangeRate
    {
        if ($currency->manual_rate !== null) {
            return new AppliedExchangeRate($baseCurrency, $currency->currency_code, $currency->manual_rate, null, Decimal::of(0), ExchangeRateSource::Manual, $currency->manual_rate_set_at);
        }

        if ($market === null) {
            return null;
        }

        $withMargin = $market->rate->multiplyBy($currency->margin_percent->add(100))->divideBy(100, self::RATE_SCALE);

        return new AppliedExchangeRate($baseCurrency, $currency->currency_code, $withMargin, $market->rate, $currency->margin_percent, ExchangeRateSource::Market, $market->asOf);
    }
}
