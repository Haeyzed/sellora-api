<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates;

use App\Shared\Money\ExchangeRates\Models\ExchangeRate;
use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes old market rates from the central database. Orders keep the rate they used, so history isn't needed for them.
 *
 * @extends TimestampRetentionPolicy<ExchangeRate>
 */
final class ExchangeRateRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'exchange_rates';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Central;
    }

    /**
     * @return Builder<ExchangeRate>
     */
    protected function query(): Builder
    {
        return ExchangeRate::query();
    }

    protected function timestampColumn(): string
    {
        return 'fetched_at';
    }
}
