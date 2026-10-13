<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates;

use App\Shared\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * The market rate between two currencies, in the one direction used everywhere: an amount in the base currency × rate = the amount in the quote currency.
 */
final readonly class CrossRate
{
    /**
     * @param  Decimal  $rate  Units of the quote currency one unit of the base currency buys, to 18 decimal places.
     * @param  CarbonImmutable  $asOf  When the older of the two market rates it comes from was fetched.
     */
    public function __construct(
        public string $baseCurrency,
        public string $quoteCurrency,
        public Decimal $rate,
        public CarbonImmutable $asOf,
    ) {}
}
