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
| For AddressFormatsTest.php and CustomerAddressTest.php
|--------------------------------------------------------------------------
*/

/**
 * Gives a seeded country (seedWorld()) exactly the states the address data lists for it, so the two sources agree, as they do for Japan in the real data.
 */
function seedStatesMatchingAddressData(string $countryCode): void
{
    $countryId = (int) DB::table('countries')->where('iso2', $countryCode)->value('id');
    $subdivisions = (new CommerceGuys\Addressing\Subdivision\SubdivisionRepository)->getList([$countryCode]);

    DB::table('states')->where('country_code', $countryCode)->delete();
    DB::table('states')->insert(array_map(
        static fn (string $code, string $name): array => worldState($countryId, $countryCode, $code, $name),
        array_map(strval(...), array_keys($subdivisions)),
        array_values($subdivisions),
    ));
    Illuminate\Support\Facades\Cache::flush();
}

/**
 * Adds two countries to the seeded world, both without states: the United Kingdom, with a strict postcode format, and the United Arab Emirates, with no postcodes.
 */
function seedAddressFormatCountries(): void
{
    foreach (['GB' => ['United Kingdom', '44', 'GBP', 'Europe/London'], 'AE' => ['United Arab Emirates', '971', 'AED', 'Asia/Dubai']] as $code => [$name, $phoneCode, $currency, $timezone]) {
        $countryId = DB::table('countries')->insertGetId(worldCountry($code, $name, $phoneCode));
        DB::table('currencies')->insert(worldCurrency($countryId, $currency));
        DB::table('timezones')->insert(['country_id' => $countryId, 'name' => $timezone]);
    }

    Illuminate\Support\Facades\Cache::flush();
}
