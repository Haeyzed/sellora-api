<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates;

use App\Shared\Money\Decimal;
use App\Shared\Money\ExchangeRates\Contracts\ExchangeRateSource;
use App\Shared\Money\ExchangeRates\Exceptions\ExchangeRateSourceUnavailableException;
use App\Shared\Money\ExchangeRates\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Fetches market exchange rates once for the whole platform, every hour (section 3.3).
 *
 * A failed fetch keeps the last rates. When none has succeeded for longer
 * than config/exchange_rates.php allows (12 hours), every run reports an
 * error, which error tracking picks up, because the outage affects every
 * store at once and presentment currencies are withdrawn at 48 hours.
 */
final class RefreshExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:refresh';

    protected $description = 'Fetch the latest market exchange rates for every store';

    public function handle(Container $container, ExchangeRates $exchangeRates): int
    {
        $fetched = false;

        if (! $container->bound(ExchangeRateSource::class)) {
            $this->components->warn('No exchange rate source is installed.');
        } else {
            try {
                $count = $this->record($container->make(ExchangeRateSource::class)->fetch());
                $this->components->info("Recorded {$count} exchange rates.");
                $fetched = true;
            } catch (ExchangeRateSourceUnavailableException $exception) {
                Log::warning('Exchange rates could not be fetched.', ['reason' => $exception->getMessage()]);
                $this->components->warn('Exchange rates could not be fetched: '.$exception->getMessage());
            }
        }

        $this->alertWhenOverdue($exchangeRates->lastFetchedAt());

        return $fetched ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Saves one answer from the provider: a row per currency, never changing an earlier one.
     */
    private function record(FetchedRates $fetchedRates): int
    {
        // The dollar is what every rate is against, so it is always there.
        $unitsPerUsd = ['USD' => Decimal::of(1), ...$fetchedRates->unitsPerUsd];
        $now = CarbonImmutable::now();
        $rows = [];

        foreach ($unitsPerUsd as $currency => $rate) {
            $rows[] = [
                'currency' => $currency,
                'units_per_usd' => $rate->roundTo(12)->toString(),
                'source' => $fetchedRates->source,
                'fetched_at' => $fetchedRates->asOf,
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ExchangeRate::query()->insert($chunk);
        }

        return count($rows);
    }

    private function alertWhenOverdue(?CarbonImmutable $lastFetchedAt): void
    {
        $alertAfterHours = config()->integer('exchange_rates.alert_after_hours');

        if ($lastFetchedAt !== null && $lastFetchedAt->greaterThan(CarbonImmutable::now()->subHours($alertAfterHours))) {
            return;
        }

        Log::error('Exchange rates have not been fetched for over '.$alertAfterHours.' hours; presentment currencies are withdrawn after '.config()->integer('exchange_rates.stale_after_hours').' hours.', [
            'last_fetched_at' => $lastFetchedAt?->toIso8601String(),
        ]);
    }
}
