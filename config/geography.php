<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Geography (App\Shared\Geography)
|--------------------------------------------------------------------------
|
| What the world reference data doesn't say, and how long it is cached.
| Countries, states and timezones come from nnjeim/world; currencies from
| ISO 4217 through App\Shared\Money\Currencies. These lists only decide a
| new store's defaults at sign-up; the merchant can change them later.
|
*/

return [

    /*
     * How long the reference data is cached, in seconds. It only changes when
     * the world data is reseeded, after which the cache should be cleared.
     */
    'cache_ttl_in_seconds' => (int) env('GEOGRAPHY_CACHE_TTL', 86400),

    /*
     * The languages a store can publish its content in (product names,
     * pages and so on), as language codes. Separate from the languages API
     * messages are translated into, which fall back to English.
     */
    'content_locales' => [
        'ar', 'de', 'en', 'es', 'fr', 'ha', 'hi', 'id', 'ig', 'it', 'ja', 'ko',
        'nl', 'pl', 'pt', 'ru', 'sw', 'tr', 'yo', 'zh',
    ],

    /* The content language a new store starts with when its country isn't listed below. */
    'default_locale' => 'en',

    /* A new store's content language by country (ISO 3166-1 alpha-2); each must be a content locale. */
    'country_locales' => [
        'AE' => 'ar', 'AO' => 'pt', 'AR' => 'es', 'AT' => 'de', 'BE' => 'fr', 'BF' => 'fr', 'BJ' => 'fr',
        'BO' => 'es', 'BR' => 'pt', 'CD' => 'fr', 'CG' => 'fr', 'CH' => 'de', 'CI' => 'fr', 'CL' => 'es',
        'CM' => 'fr', 'CN' => 'zh', 'CO' => 'es', 'CR' => 'es', 'CV' => 'pt', 'DE' => 'de', 'DO' => 'es',
        'DZ' => 'ar', 'EC' => 'es', 'EG' => 'ar', 'ES' => 'es', 'FR' => 'fr', 'GA' => 'fr', 'GN' => 'fr',
        'GT' => 'es', 'GW' => 'pt', 'HN' => 'es', 'ID' => 'id', 'IQ' => 'ar', 'IT' => 'it', 'JO' => 'ar',
        'JP' => 'ja', 'KR' => 'ko', 'KW' => 'ar', 'LB' => 'ar', 'LU' => 'fr', 'LY' => 'ar', 'MA' => 'ar',
        'MG' => 'fr', 'ML' => 'fr', 'MX' => 'es', 'MZ' => 'pt', 'NE' => 'fr', 'NI' => 'es', 'NL' => 'nl',
        'OM' => 'ar', 'PA' => 'es', 'PE' => 'es', 'PL' => 'pl', 'PT' => 'pt', 'PY' => 'es', 'QA' => 'ar',
        'RU' => 'ru', 'SA' => 'ar', 'SN' => 'fr', 'SV' => 'es', 'SY' => 'ar', 'TD' => 'fr', 'TG' => 'fr',
        'TN' => 'ar', 'TR' => 'tr', 'TW' => 'zh', 'TZ' => 'sw', 'UY' => 'es', 'VE' => 'es', 'YE' => 'ar',
    ],

    /*
     * Countries where prices are usually shown before tax, so a new store
     * there starts tax-exclusive. Everywhere else starts tax-inclusive.
     */
    'tax_exclusive_countries' => ['CA', 'US'],

    /* Countries where a new store shows weights in pounds and sizes in inches; everywhere else uses kilograms and centimetres. */
    'imperial_countries' => ['LR', 'MM', 'US'],

];
