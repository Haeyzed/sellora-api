<?php

declare(strict_types=1);

use App\Shared\Money\ExchangeRates\Contracts\ExchangeRateSource;
use App\Shared\Money\ExchangeRates\Exceptions\ExchangeRateSourceUnavailableException;
use App\Shared\Money\ExchangeRates\RefreshExchangeRatesCommand;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Integrations\OpenExchangeRates\Exceptions\UnsafeFloatPrecisionException;
use Integrations\OpenExchangeRates\OpenExchangeRatesSource;

/*
 * The platform's market rates come from Open Exchange Rates (section 2.1).
 * The app ID comes from the environment only and is never sent in the URL,
 * logged, or put in an error.
 */
uses(LazilyRefreshDatabase::class);

const OXR_TEST_APP_ID = 'secret-app-id-5f2c';

beforeEach(function (): void {
    config(['openexchangerates.app_id' => OXR_TEST_APP_ID, 'openexchangerates.base_url' => 'https://openexchangerates.test/api/']);

    $this->logged = [];
    Event::listen(MessageLogged::class, fn (MessageLogged $message) => $this->logged[] = $message->message.' '.json_encode($message->context));
});

it('is the platform\'s exchange rate source', function (): void {
    expect(app(ExchangeRateSource::class))->toBeInstanceOf(OpenExchangeRatesSource::class);
});

it('fetches the latest dollar rates exactly as sent, with the app ID in a header only', function (): void {
    Http::fake(['openexchangerates.test/*' => Http::response('{"disclaimer":"…","timestamp":1760342400,"base":"USD","rates":{"NGN":1550.123456,"EUR":0.92,"JPY":150,"BTC":0.000016234}}')]);

    $rates = app(ExchangeRateSource::class)->fetch();

    expect(array_map(static fn ($rate): string => $rate->toString(), $rates->unitsPerUsd))->toBe(['NGN' => '1550.123456', 'EUR' => '0.92', 'JPY' => '150', 'BTC' => '0.000016234'])
        ->and($rates->asOf->timestamp)->toBe(1760342400)
        ->and($rates->source)->toBe('openexchangerates');

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://openexchangerates.test/api/latest.json'
        && $request->header('Authorization') === ['Token '.OXR_TEST_APP_ID]
        && ! str_contains($request->url(), OXR_TEST_APP_ID));
});

it('fails without saying anything secret when refused, unreachable, unconfigured or answered wrongly', function (Closure $setUp): void {
    $setUp();

    try {
        app(ExchangeRateSource::class)->fetch();
        $this->fail('The fetch should have failed.');
    } catch (ExchangeRateSourceUnavailableException $exception) {
        expect($exception->getMessage())->not->toContain(OXR_TEST_APP_ID)->not->toContain('invalid_app_id');
    }
})->with([
    'refused' => [fn () => Http::fake(['*' => Http::response('{"error":true,"status":401,"message":"invalid_app_id","description":"Invalid App ID '.OXR_TEST_APP_ID.'"}', 401)])],
    'unreachable' => [fn () => Http::fake(['*' => static fn () => throw new ConnectionException('cURL error 6 for https://openexchangerates.test/api/latest.json')])],
    'unconfigured' => [fn () => config(['openexchangerates.app_id' => null])],
    'another base' => [fn () => Http::fake(['*' => Http::response(['timestamp' => 1760342400, 'base' => 'EUR', 'rates' => ['USD' => 1.08]])])],
    'a rate that isn\'t a number' => [fn () => Http::fake(['*' => Http::response(['timestamp' => 1760342400, 'base' => 'USD', 'rates' => ['NGN' => 'lots']])])],
    'a rate of zero' => [fn () => Http::fake(['*' => Http::response(['timestamp' => 1760342400, 'base' => 'USD', 'rates' => ['NGN' => 0]])])],
]);

it('refuses loudly to read rates when PHP would round them, and records none', function (): void {
    Http::fake(['openexchangerates.test/*' => Http::response('{"timestamp":1760342400,"base":"USD","rates":{"EUR":0.92}}')]);
    $serializePrecision = (string) ini_get('serialize_precision');
    ini_set('serialize_precision', '17');

    try {
        // With 17, the rate would have been read as 0.92000000000000004.
        expect(json_encode(0.92))->toBe('0.92000000000000004')
            ->and(fn () => app(ExchangeRateSource::class)->fetch())->toThrow(UnsafeFloatPrecisionException::class, 'serialize_precision is "17", not -1')
            ->and(fn () => $this->artisan(RefreshExchangeRatesCommand::class)->run())->toThrow(UnsafeFloatPrecisionException::class);
    } finally {
        ini_set('serialize_precision', $serializePrecision);
    }

    expect(DB::connection('central')->table('exchange_rates')->count())->toBe(0);
    Http::assertNothingSent();
});

it('never logs the app ID when the hourly refresh fails', function (): void {
    Http::fake(['*' => Http::response('{"error":true,"status":401,"description":"Invalid App ID '.OXR_TEST_APP_ID.'"}', 401)]);

    $this->artisan(RefreshExchangeRatesCommand::class)->assertFailed();

    expect($this->logged)->not->toBeEmpty()
        ->and(implode("\n", $this->logged))->not->toContain(OXR_TEST_APP_ID);
});
