<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\ContentLocale;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\CurrencyCode;
use App\Shared\Geography\Rules\StateCode;
use App\Shared\Geography\Rules\Timezone;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/*
 * Section 2.1 and 3.3: world reference data is read only through
 * App\Shared\Geography, identified by ISO codes. Currencies come from ISO
 * 4217, not the reference data. A country sets a new store's currency,
 * timezone, content language and tax mode. The read-only endpoints are
 * served on the central domain and on every store's domain, cached and
 * rate-limited.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @return array<string, mixed>
 */
function geographyCountry(string $code, array $countries): array
{
    return collect($countries)->firstWhere('code', $code) ?? [];
}

it('lists the countries stores can use by ISO code, with what a new store there starts with', function (): void {
    $countries = $this->getJson(centralUrl('/api/v1/geography/countries'))->assertOk()->json('data');

    expect(array_column($countries, 'code'))->toBe(['BT', 'DE', 'JP', 'NG', 'US'])
        ->and(geographyCountry('NG', $countries))->toMatchArray([
            'name' => 'Nigeria', 'phone_code' => '234', 'currency' => 'NGN', 'timezones' => ['Africa/Lagos'],
            'store_defaults' => ['currency' => 'NGN', 'locale' => 'en', 'tax_mode' => 'inclusive'],
        ])
        ->and(geographyCountry('US', $countries))->toMatchArray([
            'timezones' => ['America/Chicago', 'America/New_York'],
            'store_defaults' => ['currency' => 'USD', 'locale' => 'en', 'tax_mode' => 'exclusive'],
        ])
        ->and(geographyCountry('DE', $countries)['store_defaults'])->toBe(['currency' => 'EUR', 'locale' => 'de', 'tax_mode' => 'inclusive'])
        ->and(geographyCountry('JP', $countries)['store_defaults']['locale'])->toBe('ja')
        ->and(geographyCountry('BT', $countries)['currency'])->toBe('BTN');
});

it('takes a country\'s currency from ISO 4217, not from the reference data', function (): void {
    DB::table('currencies')->where('code', 'NGN')->update(['code' => 'XYZ']);
    DB::table('currencies')->where('code', 'BTN')->update(['code' => 'INR']);

    $geography = app(Geography::class);

    expect($geography->country('NG')?->currencyCode)->toBe('NGN')
        ->and($geography->country('BT')?->currencyCode)->toBe('INR');
});

it('shows one country by code in any case, and answers 404 for one it doesn\'t support', function (): void {
    $this->getJson(centralUrl('/api/v1/geography/countries/ng'))->assertOk()->assertJsonPath('data.code', 'NG');

    $this->getJson(centralUrl('/api/v1/geography/countries/FR'))->assertNotFound()->assertJsonPath('code', 'country_not_found');
    $this->getJson(centralUrl('/api/v1/geography/countries/ZZ/states'))->assertNotFound()->assertJsonPath('code', 'country_not_found');
});

it('lists a country\'s states by name, identified by their codes', function (): void {
    $this->getJson(centralUrl('/api/v1/geography/countries/NG/states'))->assertOk()->assertExactJson(['data' => [
        ['code' => 'FC', 'name' => 'Abuja Federal Capital Territory', 'type' => 'state'],
        ['code' => 'LA', 'name' => 'Lagos', 'type' => 'state'],
    ]]);

    $this->getJson(centralUrl('/api/v1/geography/countries/DE/states'))->assertOk()->assertExactJson(['data' => []]);
});

it('follows ISO 3166 rather than the dataset: states belong to the country they are filed under, and another country\'s codes are left out', function (): void {
    $hongKong = DB::table('countries')->insertGetId(worldCountry('HK', 'Hong Kong', '852'));
    $cambodia = DB::table('countries')->insertGetId(worldCountry('KH', 'Cambodia', '855'));
    $russia = DB::table('countries')->insertGetId(worldCountry('RU', 'Russia', '7'));
    $unitedStates = DB::table('countries')->where('iso2', 'US')->value('id');

    DB::table('states')->insert([
        // The dataset labels this Hong Kong district with Cambodia's code.
        worldState($hongKong, 'KH', 'NTP', 'Tai Po District'),
        worldState($cambodia, 'KH', '12', 'Phnom Penh'),
        // Sevastopol is filed under Russia with Ukraine's ISO 3166-2 code.
        worldState($russia, 'RU', 'UA-40', 'Sevastopol'),
        worldState($russia, 'RU', 'MOW', 'Moscow'),
        worldState($russia, 'RU', 'RU-AD', 'Adygea'),
        worldState($unitedStates, 'US', 'UM-81', 'Baker Island'),
    ]);

    $codes = fn (string $country): array => array_column($this->getJson(centralUrl("/api/v1/geography/countries/{$country}/states"))->assertOk()->json('data'), 'code');

    expect($codes('HK'))->toBe(['NTP'])
        ->and($codes('KH'))->toBe(['12'])
        ->and($codes('RU'))->toBe(['AD', 'MOW'])
        ->and($codes('US'))->toBe(['CA', 'NY'])
        ->and(app(Geography::class)->state('RU', 'UA-40'))->toBeNull()
        ->and(app(Geography::class)->state('RU', '40'))->toBeNull();
});

it('lists ISO 4217 currencies in use with their decimal places, current timezones without deprecated aliases, and content languages', function (): void {
    $currencies = collect($this->getJson(centralUrl('/api/v1/geography/currencies'))->assertOk()->json('data'))->keyBy('code');
    $timezones = $this->getJson(centralUrl('/api/v1/geography/timezones'))->assertOk()->json('data');

    expect($currencies['JPY']['decimal_places'])->toBe(0)
        ->and($currencies['KWD']['decimal_places'])->toBe(3)
        ->and($currencies['NGN']['decimal_places'])->toBe(2)
        ->and($currencies)->not->toHaveKey('DEM')
        ->and($timezones)->toContain('Africa/Lagos')->not->toContain('US/Eastern');

    $this->getJson(centralUrl('/api/v1/geography/locales'))->assertOk()->assertExactJson(['data' => config()->array('geography.content_locales')]);
});

it('serves the same data on every store\'s domain, but not for a store that doesn\'t serve requests or an unknown domain', function (): void {
    $store = createStore('geography-store');

    $this->getJson(storeUrl('geography-store', '/api/v1/geography/countries/NG/states'))->assertOk()->assertJsonCount(2, 'data');
    tenancy()->end();

    $store->update(['status' => TenantStatus::Closed, 'status_before_closing' => TenantStatus::Active, 'closed_at' => now(), 'purge_after' => now()->addDays(90)]);
    $this->getJson(storeUrl('geography-store', '/api/v1/geography/countries'))->assertServiceUnavailable()->assertJsonPath('code', 'store_unavailable');
    tenancy()->end();

    $this->getJson(storeUrl('nobody-here', '/api/v1/geography/countries'))->assertNotFound();
});

it('lets browsers cache the data, caches it on the server, and rate-limits it', function (): void {
    config()->set('api.rate_limits.public', 3);

    $this->getJson(centralUrl('/api/v1/geography/countries'))->assertOk()->assertHeader('Cache-Control', 'max-age=3600, public');

    DB::table('countries')->where('iso2', 'NG')->update(['name' => 'Changed after caching']);
    $this->getJson(centralUrl('/api/v1/geography/countries/NG'))->assertOk()->assertJsonPath('data.name', 'Nigeria');

    $this->getJson(centralUrl('/api/v1/geography/currencies'))->assertOk();
    $this->getJson(centralUrl('/api/v1/geography/timezones'))->assertTooManyRequests();
});

it('validates country, state, timezone, currency and content language codes for forms', function (): void {
    $passes = static fn (string $field, mixed $value, object $rule): bool => Validator::make([$field => $value], [$field => [$rule]])->passes();

    expect($passes('country', 'NG', new CountryCode))->toBeTrue()
        ->and($passes('country', 'ng', new CountryCode))->toBeFalse()
        ->and($passes('country', 'FR', new CountryCode))->toBeFalse()
        ->and($passes('state', 'LA', new StateCode('NG')))->toBeTrue()
        ->and($passes('state', 'CA', new StateCode('NG')))->toBeFalse()
        ->and($passes('state', 'LA', new StateCode(null)))->toBeFalse()
        ->and($passes('timezone', 'Africa/Lagos', new Timezone))->toBeTrue()
        ->and($passes('timezone', 'US/Eastern', new Timezone))->toBeFalse()
        ->and($passes('currency', 'JPY', new CurrencyCode))->toBeTrue()
        ->and($passes('currency', 'DEM', new CurrencyCode))->toBeFalse()
        ->and($passes('currency', 'usd', new CurrencyCode))->toBeFalse()
        ->and($passes('locale', 'fr', new ContentLocale))->toBeTrue()
        ->and($passes('locale', 'xx', new ContentLocale))->toBeFalse();
});
