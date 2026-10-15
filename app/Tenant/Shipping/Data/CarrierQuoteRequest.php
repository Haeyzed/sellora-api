<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

/**
 * What a carrier is asked to quote: parcels from the store to the shopper, priced in a currency.
 */
final readonly class CarrierQuoteRequest
{
    /**
     * @param  list<Parcel>  $parcels
     * @param  string  $currency  ISO 4217: the basket's currency. A carrier may answer in it or in the store's base currency.
     */
    public function __construct(
        public CarrierAddress $origin,
        public CarrierAddress $destination,
        public array $parcels,
        public string $currency,
    ) {}
}
