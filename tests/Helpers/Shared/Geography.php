<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| For GeographyTest.php
|--------------------------------------------------------------------------
*/

/**
 * @return array<string, mixed>
 */
function geographyCountry(string $code, array $countries): array
{
    return collect($countries)->firstWhere('code', $code) ?? [];
}

/*
|--------------------------------------------------------------------------
| For AddressFormatsTest.php, CustomerAddressTest.php and StateTaxRatesTest.php
|--------------------------------------------------------------------------
*/

/**
 * Adds one country to the seeded world (seedWorld()), with its currency and one timezone.
 */
function seedWorldCountry(string $code, string $name, string $phoneCode, string $currency, string $timezone): void
{
    $countryId = DB::table('countries')->insertGetId(worldCountry($code, $name, $phoneCode));
    DB::table('currencies')->insert(worldCurrency($countryId, $currency));
    DB::table('timezones')->insert(['country_id' => $countryId, 'name' => $timezone]);

    Illuminate\Support\Facades\Cache::flush();
}

/**
 * Adds two countries to the seeded world: the United Kingdom, with a strict postcode format, and the United Arab Emirates, with no postcodes.
 */
function seedAddressFormatCountries(): void
{
    seedWorldCountry('GB', 'United Kingdom', '44', 'GBP', 'Europe/London');
    seedWorldCountry('AE', 'United Arab Emirates', '971', 'AED', 'Asia/Dubai');
}
