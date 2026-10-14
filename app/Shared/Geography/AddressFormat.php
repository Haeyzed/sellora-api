<?php

declare(strict_types=1);

namespace App\Shared\Geography;

/**
 * What a country's postal addresses need (section 3.3): which fields are required, and what a postcode looks like.
 *
 * Built by AddressFormats from Google's address data (commerceguys/addressing),
 * the same data Geography's states come from. Where the data requires a state
 * but lists none for the country, the state is required free text.
 */
final readonly class AddressFormat
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2, such as "NG".
     * @param  string|null  $postalCodePattern  A regular expression the whole postcode must match, without delimiters; null when the country has none.
     */
    public function __construct(
        public string $countryCode,
        public bool $requiresCity,
        public bool $requiresState,
        public bool $requiresPostalCode,
        public ?string $postalCodePattern,
    ) {}

    /**
     * Whether a postcode has this country's format (any case). Always true for a country without one.
     */
    public function acceptsPostalCode(string $postalCode): bool
    {
        if ($this->postalCodePattern === null) {
            return true;
        }

        return preg_match('~^(?:'.$this->postalCodePattern.')$~i', trim($postalCode)) === 1;
    }
}
