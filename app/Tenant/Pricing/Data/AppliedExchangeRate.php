<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Decimal;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use Carbon\CarbonImmutable;

/**
 * The rate a store converts its base prices at for one currency, with everything an order saves about it (section 3.3).
 *
 * One direction everywhere: an amount in the base currency × rate = the
 * amount in this currency. The market rate and the margin stay separate.
 */
final readonly class AppliedExchangeRate
{
    /**
     * @param  Decimal  $rate  What prices are converted at.
     * @param  Decimal|null  $marketRate  The market rate it came from; null for a manual rate or the base currency.
     * @param  Decimal  $marginPercent  The store's margin on the market rate; zero for a manual rate.
     * @param  CarbonImmutable|null  $asOf  When the market rate was fetched or the manual rate set; null for the base currency.
     */
    public function __construct(
        public string $baseCurrency,
        public string $currency,
        public Decimal $rate,
        public ?Decimal $marketRate,
        public Decimal $marginPercent,
        public ExchangeRateSource $source,
        public ?CarbonImmutable $asOf,
    ) {}

    /**
     * The base currency, which needs no conversion.
     */
    public static function base(string $baseCurrency): self
    {
        return new self($baseCurrency, $baseCurrency, Decimal::of(1), null, Decimal::of(0), ExchangeRateSource::Base, null);
    }
}
