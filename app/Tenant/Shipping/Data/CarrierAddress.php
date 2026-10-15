<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

/**
 * An address a carrier quotes from or to. Personal data when it is a shopper's: sent to the carrier only to quote, never logged or cached readably.
 */
final readonly class CarrierAddress
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2.
     * @param  string|null  $stateCode  The state's code within the country, or free text where it has no list.
     */
    public function __construct(
        public string $countryCode,
        public ?string $stateCode = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $line1 = null,
    ) {}

    /**
     * Its parts in a fixed order, for hashing into a cache key.
     *
     * @return list<string|null>
     */
    public function parts(): array
    {
        return [$this->countryCode, $this->stateCode, $this->postalCode, $this->city, $this->line1];
    }
}
