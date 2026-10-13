<?php

declare(strict_types=1);

namespace Integrations\OpenExchangeRates;

use App\Shared\Money\Decimal;
use App\Shared\Money\ExchangeRates\Contracts\ExchangeRateSource;
use App\Shared\Money\ExchangeRates\Exceptions\ExchangeRateSourceUnavailableException;
use App\Shared\Money\ExchangeRates\FetchedRates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Integrations\OpenExchangeRates\Exceptions\UnsafeFloatPrecisionException;
use InvalidArgumentException;
use Throwable;

/**
 * Market rates from Open Exchange Rates: the latest rates against USD, for the whole platform.
 *
 * The app ID comes from the environment only and travels in the
 * Authorization header, never in the URL, so it can't end up in logs or
 * error reports. Failures report only the HTTP status, never the response.
 */
final readonly class OpenExchangeRatesSource implements ExchangeRateSource
{
    public const string SOURCE = 'openexchangerates';

    public function __construct(private HttpFactory $http) {}

    /**
     * @throws UnsafeFloatPrecisionException When PHP can't read the rates exactly; not caught as an outage, so it is reported.
     */
    public function fetch(): FetchedRates
    {
        $serializePrecision = (string) ini_get('serialize_precision');

        if ($serializePrecision !== '-1') {
            throw UnsafeFloatPrecisionException::for($serializePrecision);
        }

        $appId = config('openexchangerates.app_id');

        if (! is_string($appId) || $appId === '') {
            throw new ExchangeRateSourceUnavailableException('Open Exchange Rates has no app ID configured (OPEN_EXCHANGE_RATES_APP_ID).');
        }

        try {
            $response = $this->http
                ->baseUrl(config()->string('openexchangerates.base_url'))
                ->timeout(config()->integer('openexchangerates.timeout_in_seconds'))
                ->withHeaders(['Authorization' => 'Token '.$appId])
                ->acceptJson()
                ->get('latest.json');
        } catch (ConnectionException) {
            throw new ExchangeRateSourceUnavailableException('Open Exchange Rates could not be reached.');
        }

        if (! $response->successful()) {
            throw new ExchangeRateSourceUnavailableException("Open Exchange Rates refused the request (HTTP {$response->status()}).");
        }

        $rates = $response->json('rates');
        $timestamp = $response->json('timestamp');

        if ($response->json('base') !== 'USD' || ! is_array($rates) || ! is_int($timestamp)) {
            throw new ExchangeRateSourceUnavailableException('Open Exchange Rates answered without usable rates.');
        }

        try {
            return new FetchedRates(self::unitsPerUsd($rates), CarbonImmutable::createFromTimestampUTC($timestamp), self::SOURCE);
        } catch (InvalidArgumentException) {
            throw new ExchangeRateSourceUnavailableException('Open Exchange Rates answered with an unusable rate.');
        }
    }

    /**
     * The rates as exact decimals. JSON numbers arrive as floats; their shortest exact form ("1550.123456") is what the provider sent, which json_encode() gives only with serialize_precision = -1 (checked in fetch()).
     *
     * @param  array<mixed>  $rates
     * @return array<string, Decimal>
     *
     * @throws InvalidArgumentException When a rate isn't a number.
     */
    private static function unitsPerUsd(array $rates): array
    {
        $unitsPerUsd = [];

        foreach ($rates as $currency => $rate) {
            if (! is_int($rate) && ! is_float($rate)) {
                throw new InvalidArgumentException("The rate of {$currency} is not a number.");
            }

            try {
                $unitsPerUsd[(string) $currency] = Decimal::of(is_int($rate) ? $rate : json_encode($rate, JSON_THROW_ON_ERROR));
            } catch (Throwable) {
                throw new InvalidArgumentException("The rate of {$currency} is not a number.");
            }
        }

        return $unitsPerUsd;
    }
}
