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

    /*
     * How many years a store keeps the buyer's personal data on settled
     * orders, by store country, before it is anonymised (the amounts and tax
     * stay). These follow common rules for keeping financial records, but
     * laws differ and change: merchants should confirm their period with
     * their accountant, and can set their own (3 to 30 years).
     */
    'order_retention_years' => [
        'NG' => 6,
        'GB' => 6,
        'DE' => 10,
    ],

    /* For store countries not listed above: the longest common period, to be safe. */
    'order_retention_default_years' => 10,

    /*
     * How many years a store must keep its invoices and credit notes as
     * issued, buyer's details included, by store country, counted from the
     * end of the year each was issued in. Erasure requests can't shorten it:
     * once it ends, the buyer's details are cleared from documents whose
     * order has been anonymised. Checked October 2026; laws change, so
     * re-check before relying on them:
     * - DE: 8 years for invoices and other accounting records since 2025
     *   (Bürokratieentlastungsgesetz IV: § 147 (3) AO, § 14b (1) UStG).
     * - GB: 6 years (HMRC VAT Notice 700/21, record keeping).
     * - FR: 10 years for accounting documents (Code de commerce, L123-22);
     *   tax law alone asks 6 (LPF L102 B), so the longer applies.
     * - KE: 5 years from the end of the reporting period (Tax Procedures
     *   Act 2015, s. 23).
     * - ZA: 5 years from the return's submission (Tax Administration Act
     *   2011, s. 29), longer during an audit or dispute.
     * - NG: 6 years, as under the earlier law (CITA s. 66, FIRSEA s. 55);
     *   not yet confirmed against the Nigeria Tax Administration Act 2025
     *   (record keeping around s. 102). To confirm.
     * - US: 7 years, the longest federal period the IRS names (Publication
     *   583); states' sales tax periods are mostly shorter.
     */
    'tax_record_retention_years' => [
        'DE' => 8,
        'GB' => 6,
        'FR' => 10,
        'KE' => 5,
        'ZA' => 5,
        'NG' => 6,
        'US' => 7,
    ],

    /* For store countries not listed above: 10 years, the longest common period, to be safe. */
    'tax_record_retention_default_years' => 10,

    /*
     * What a store's tax registration number is usually called on invoices,
     * by store country, until the store sets its own label.
     */
    'tax_registration_labels' => [
        'NG' => 'TIN',
        'GB' => 'VAT',
        'DE' => 'USt-IdNr.',
        'FR' => 'TVA',
        'KE' => 'PIN',
        'ZA' => 'VAT',
    ],

    /* For store countries not listed above. */
    'tax_registration_default_label' => 'Tax ID',

    /*
     * The subdivision codes stores may have saved, by country, as recorded
     * from commerceguys/addressing. A package update that changes them is
     * caught by a test and listed by geography:subdivision-changes.
     */
    'subdivision_codes_path' => resource_path('geography/subdivision-codes.json'),

];
