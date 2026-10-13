<?php

declare(strict_types=1);

namespace Tests\Fixtures\ExchangeRates;

use App\Shared\Money\Decimal;
use App\Shared\Money\ExchangeRates\Contracts\ExchangeRateSource;
use App\Shared\Money\ExchangeRates\Exceptions\ExchangeRateSourceUnavailableException;
use App\Shared\Money\ExchangeRates\FetchedRates;
use Carbon\CarbonImmutable;

/**
 * An exchange rate provider that answers with hand-picked rates, or fails when told to.
 */
final class FakeExchangeRateSource implements ExchangeRateSource
{
    public bool $fails = false;

    /**
     * @param  array<string, string>  $unitsPerUsd
     */
    public function __construct(public array $unitsPerUsd = ['NGN' => '1500', 'EUR' => '0.9', 'JPY' => '150', 'KWD' => '0.307']) {}

    public function fetch(): FetchedRates
    {
        if ($this->fails) {
            throw new ExchangeRateSourceUnavailableException('The provider refused the request (HTTP 503).');
        }

        return new FetchedRates(array_map(static fn (string $rate): Decimal => Decimal::of($rate), $this->unitsPerUsd), CarbonImmutable::now(), 'fake');
    }
}
