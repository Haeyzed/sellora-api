<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

/**
 * What the shopper changes about their cart: the currency it is priced in, where they are (country and state, for the tax and shipping estimate), and the shipping method they chose. Anything not given stays as it is.
 */
final readonly class CartSettings
{
    /**
     * @param  string|null  $currency  ISO 4217; null for the store's own.
     * @param  string|null  $destinationCountryCode  ISO 3166-1 alpha-2; null for the store's own country. Changing it clears the state unless one is given.
     * @param  string|null  $destinationStateCode  A state of the cart's country, as sent; null for none.
     * @param  string|null  $shippingMethodId  The chosen method's public ID; null for none.
     */
    public function __construct(
        public bool $changesCurrency = false,
        public ?string $currency = null,
        public bool $changesDestination = false,
        public ?string $destinationCountryCode = null,
        public bool $changesState = false,
        public ?string $destinationStateCode = null,
        public bool $changesShippingMethod = false,
        public ?string $shippingMethodId = null,
    ) {}
}
