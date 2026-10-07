<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

/**
 * The store details the platform keeps a copy of: what platform admins see and what platform emails to the owner use.
 */
final readonly class StoreProfileDetails
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2, such as "NG".
     * @param  string  $currencyCode  The base currency, ISO 4217, such as "NGN".
     * @param  string  $timezone  IANA, such as "Africa/Lagos".
     * @param  string  $locale  The store's default language, such as "en"; platform emails to the owner use it.
     */
    public function __construct(
        public string $name,
        public string $countryCode,
        public string $currencyCode,
        public string $timezone,
        public string $locale,
    ) {}
}
