<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

/**
 * What the shopper changes about their cart: the currency it is priced in, and the country tax is estimated to. Anything not given stays as it is.
 */
final readonly class CartSettings
{
    /**
     * @param  string|null  $currency  ISO 4217; null for the store's own.
     * @param  string|null  $destinationCountryCode  ISO 3166-1 alpha-2; null for the store's own country.
     */
    public function __construct(
        public bool $changesCurrency = false,
        public ?string $currency = null,
        public bool $changesDestination = false,
        public ?string $destinationCountryCode = null,
    ) {}
}
