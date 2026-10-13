<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates;

use App\Shared\Money\Decimal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * One answer from the exchange rate provider: how many units of each currency one US dollar buys, as of a moment.
 */
final readonly class FetchedRates
{
    /**
     * @param  array<string, Decimal>  $unitsPerUsd  By ISO 4217 code, such as ["NGN" => 1550.25, "EUR" => 0.92].
     * @param  CarbonImmutable  $asOf  When the provider says the rates were taken.
     * @param  string  $source  The provider, such as "openexchangerates".
     *
     * @throws InvalidArgumentException When a code isn't three capital letters or a rate isn't above zero.
     */
    public function __construct(
        public array $unitsPerUsd,
        public CarbonImmutable $asOf,
        public string $source,
    ) {
        foreach ($unitsPerUsd as $currency => $rate) {
            if (preg_match('/^[A-Z]{3}$/', (string) $currency) !== 1 || ! $rate->isGreaterThan(0)) {
                throw new InvalidArgumentException("Unusable rate for \"{$currency}\".");
            }
        }
    }
}
