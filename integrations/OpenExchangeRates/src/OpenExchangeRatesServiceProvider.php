<?php

declare(strict_types=1);

namespace Integrations\OpenExchangeRates;

use App\Shared\Money\ExchangeRates\Contracts\ExchangeRateSource;
use Illuminate\Support\ServiceProvider;

/**
 * The platform's market exchange rate provider (section 2.1).
 *
 * Platform-level: it serves every store at once, so it is a plain service
 * provider, not a plan-gated IntegrationServiceProvider.
 */
final class OpenExchangeRatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/openexchangerates.php', 'openexchangerates');
        $this->app->bind(ExchangeRateSource::class, OpenExchangeRatesSource::class);
    }
}
