<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Contracts;

use App\Tenant\Shipping\Data\CarrierQuoteRequest;
use App\Tenant\Shipping\Data\CarrierRate;
use App\Tenant\Shipping\Exceptions\CarrierUnavailableException;

/**
 * A shipping carrier's live rates, implemented by an integration (section 8): core never names a carrier, it asks this.
 *
 * Integrations register their carrier with ShippingCarrierRegistry from
 * their service provider; core uses it only while the integration is
 * Enabled for the store. Booking labels and tracking come with Orders.
 */
interface ShippingCarrier
{
    /**
     * Its stable key, such as "acme_express"; shipping methods name it.
     */
    public function key(): string;

    /**
     * Its name for staff, such as "Acme Express".
     */
    public function name(): string;

    /**
     * The services a method can use, by code, such as ["standard" => "Standard (3-5 days)"].
     *
     * @return array<string, string>
     */
    public function services(): array;

    /**
     * Its live rates for the parcels, one per service it offers there; a service it doesn't offer is left out.
     *
     * Must give up within the timeout: core treats a slower answer as no answer.
     * Never log the addresses.
     *
     * @return list<CarrierRate>
     *
     * @throws CarrierUnavailableException When it can't quote now (an outage, a refusal, a malformed answer).
     */
    public function quote(CarrierQuoteRequest $request, int $timeoutSeconds): array;
}
