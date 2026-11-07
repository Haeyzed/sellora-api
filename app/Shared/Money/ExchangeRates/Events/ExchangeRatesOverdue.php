<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * No exchange rate fetch has succeeded for longer than the alert limit (config exchange_rates.alert_after_hours), so every store's extra currencies are heading towards being withdrawn.
 */
final readonly class ExchangeRatesOverdue
{
    use Dispatchable;

    public function __construct(
        public ?CarbonImmutable $lastFetchedAt,
        public int $alertAfterHours,
        public int $staleAfterHours,
    ) {}
}
