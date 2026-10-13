<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Money\ExchangeRates\Contracts\ExchangeRateSource;
use App\Shared\Money\ExchangeRates\ExchangeRateRetention;
use App\Shared\Money\ExchangeRates\ExchangeRates;
use App\Shared\Money\ExchangeRates\Models\ExchangeRate;
use App\Shared\Money\ExchangeRates\RefreshExchangeRatesCommand;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\ExchangeRates\FakeExchangeRateSource;

/*
 * Sections 2.1 and 3.3: market rates are fetched hourly for the whole
 * platform, against USD, and every fetch is kept until the retention period
 * ends. Rates go in one direction: base × rate = quote. When no fetch has
 * succeeded for 12 hours, every run reports an error.
 */
uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->source = new FakeExchangeRateSource;
    app()->instance(ExchangeRateSource::class, $this->source);

    $this->logged = [];
    Event::listen(MessageLogged::class, fn (MessageLogged $message) => $this->logged[] = [$message->level, $message->message]);
});

/**
 * @return list<string>
 */
function loggedAt(string $level): array
{
    return array_values(array_map(static fn (array $entry): string => $entry[1], array_filter(test()->logged, static fn (array $entry): bool => $entry[0] === $level)));
}

it('records every currency against the dollar, the dollar included, and keeps earlier fetches', function (): void {
    $this->artisan(RefreshExchangeRatesCommand::class)->assertSuccessful();
    CarbonImmutable::setTestNow(now()->addHour());
    $this->source->unitsPerUsd['NGN'] = '1510';
    $this->artisan(RefreshExchangeRatesCommand::class)->assertSuccessful();
    CarbonImmutable::setTestNow();

    expect(ExchangeRate::query()->where('currency', 'NGN')->count())->toBe(2)
        ->and(ExchangeRate::query()->where('currency', 'USD')->value('units_per_usd')?->toString())->toBe('1.000000000000')
        ->and(app(ExchangeRates::class)->crossRate('USD', 'NGN')?->rate->toString())->toStartWith('1510.');
});

it('works out the rate between any two currencies, in one direction, as of the older of the two', function (): void {
    $this->artisan(RefreshExchangeRatesCommand::class)->assertSuccessful();
    $rates = app(ExchangeRates::class);

    // 1 NGN = 1 / 1500 USD; 1 USD = 150 JPY; 1 NGN = 0.307 / 1500 KWD, kept to 18 places so the weak naira loses nothing.
    expect($rates->crossRate('NGN', 'USD')?->rate->toString())->toBe('0.000666666666666667')
        ->and($rates->crossRate('USD', 'JPY')?->rate->toString())->toBe('150.000000000000000000')
        ->and($rates->crossRate('NGN', 'KWD')?->rate->toString())->toBe('0.000204666666666667')
        ->and($rates->crossRate('NGN', 'XAF'))->toBeNull()
        ->and($rates->crossRates('NGN', ['USD', 'EUR', 'XAF']))->toHaveKeys(['USD', 'EUR'])->not->toHaveKey('XAF');

    // An older rate for one side makes the pair as old as it.
    $old = now()->subHours(30)->toImmutable();
    DB::table('exchange_rates')->insert(['currency' => 'GHS', 'units_per_usd' => '15', 'source' => 'fake', 'fetched_at' => $old, 'created_at' => now()]);
    expect($rates->crossRate('NGN', 'GHS')?->asOf->timestamp)->toBe($old->timestamp);
});

it('keeps the last rates when a fetch fails, and reports an error once none has succeeded for 12 hours', function (): void {
    $this->artisan(RefreshExchangeRatesCommand::class)->assertSuccessful();
    $this->source->fails = true;

    CarbonImmutable::setTestNow(now()->addHours(11));
    $this->artisan(RefreshExchangeRatesCommand::class)->assertFailed();
    expect(loggedAt('warning'))->toHaveCount(1)->and(loggedAt('error'))->toBe([]);

    CarbonImmutable::setTestNow(now()->addHours(2));
    $this->artisan(RefreshExchangeRatesCommand::class)->assertFailed();
    expect(loggedAt('error'))->toHaveCount(1)
        ->and(loggedAt('error')[0])->toContain('12 hours')
        ->and(ExchangeRate::query()->where('currency', 'NGN')->count())->toBe(1);
    CarbonImmutable::setTestNow();
});

it('reports an error when rates have never been fetched or no source is installed', function (): void {
    $this->source->fails = true;
    $this->artisan(RefreshExchangeRatesCommand::class)->assertFailed();
    expect(loggedAt('error'))->toHaveCount(1);

    app()->offsetUnset(ExchangeRateSource::class);
    $this->artisan(RefreshExchangeRatesCommand::class)->assertFailed()->expectsOutputToContain('No exchange rate source');
    expect(loggedAt('error'))->toHaveCount(2);
});

it('never changes a recorded rate, and deletes old ones only through retention', function (): void {
    $this->artisan(RefreshExchangeRatesCommand::class)->assertSuccessful();

    // In a savepoint, so the refusal doesn't end the test's own transaction.
    expect(static fn () => DB::transaction(static fn (): int => DB::table('exchange_rates')->where('currency', 'NGN')->update(['units_per_usd' => '1'])))->toThrow(QueryException::class);

    DB::table('exchange_rates')->insert(['currency' => 'NGN', 'units_per_usd' => '900', 'source' => 'fake', 'fetched_at' => now()->subDays(401), 'created_at' => now()]);
    $deleted = app(ExchangeRateRetention::class)->purgeOlderThan(now()->subDays(config()->integer('retention.periods.exchange_rates'))->toImmutable());

    expect($deleted)->toBe(1)->and(ExchangeRate::query()->where('currency', 'NGN')->count())->toBe(1);
});

it('runs every hour', function (): void {
    $event = collect(app(Schedule::class)->events())->first(static fn ($event): bool => str_contains((string) $event->command, 'exchange-rates:refresh'));

    expect($event?->expression)->toBe('0 * * * *');
});

it('shows platform admins every fetched rate, newest first, a page at a time', function (): void {
    $this->artisan(RefreshExchangeRatesCommand::class)->assertSuccessful();
    $admin = PlatformAdmin::factory()->create();
    enableTwoFactor($admin);
    $token = app(AccessTokenIssuer::class)->issue($admin, PlatformAdmin::GUARD, 'test')->plainTextToken;

    $this->getJson(centralUrl('/api/v1/platform/exchange-rates'))->assertUnauthorized();
    forgetSignIns();

    $page = $this->withToken($token)->getJson(centralUrl('/api/v1/platform/exchange-rates?currency=NGN'))->assertOk();
    expect($page->json('data'))->toHaveCount(1)
        ->and($page->json('data.0'))->toMatchArray(['currency' => 'NGN', 'units_per_usd' => '1500.000000000000', 'source' => 'fake'])
        ->and($page->json('last_fetched_at'))->toBeString()
        ->and($page->json('meta'))->toHaveKey('next_cursor');
});
