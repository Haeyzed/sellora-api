<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

/**
 * A country, or one of its states, as a zone covers it or a shopper ships to it.
 */
final readonly class ShippingRegion
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2, such as "NG".
     * @param  string|null  $stateCode  The state's code within the country (Geography), such as "LA"; null for the whole country.
     */
    public function __construct(
        public string $countryCode,
        public ?string $stateCode = null,
    ) {}

    /**
     * Its ISO 3166-2 style name, such as "NG" or "NG-LA", for messages and audits.
     */
    public function label(): string
    {
        return $this->stateCode === null ? $this->countryCode : $this->countryCode.'-'.$this->stateCode;
    }
}
