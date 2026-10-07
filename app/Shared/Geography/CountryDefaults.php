<?php

declare(strict_types=1);

namespace App\Shared\Geography;

use App\Shared\Money\TaxMode;

/**
 * What a new store's country sets for it at sign-up (section 9.1). The merchant can change each later; changing the country doesn't apply them again.
 */
final readonly class CountryDefaults
{
    /**
     * @param  list<string>  $timezones  The country's timezones; the merchant chooses one when there are several.
     * @param  string  $locale  The store's first content language, one of the content locales.
     */
    public function __construct(
        public string $countryCode,
        public string $currencyCode,
        public array $timezones,
        public string $locale,
        public TaxMode $taxMode,
    ) {}
}
