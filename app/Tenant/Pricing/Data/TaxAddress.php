<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

/**
 * Where tax is worked out from or to: a country, and its state when known, by ISO codes.
 */
final readonly class TaxAddress
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2, such as "NG".
     * @param  string|null  $stateCode  The state's code within the country, such as "LA".
     * @param  string|null  $postalCode  Free text; formats differ by country. Rate tables ignore it; outside tax services may use it.
     */
    public function __construct(
        public string $countryCode,
        public ?string $stateCode = null,
        public ?string $postalCode = null,
    ) {}
}
