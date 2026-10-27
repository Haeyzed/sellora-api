<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Orders\Contracts\ShipmentDrivers;
use App\Tenant\Orders\Models\Shipment;

/**
 * Tells Orders whether one of the store's drivers has a shipment: a delivery of it is assigned, picked up, on the way, or failed and not yet resolved.
 */
final readonly class OpenDeliveries implements ShipmentDrivers
{
    public function hasDelivery(Shipment $shipment): bool
    {
        return Delivery::query()->where('shipment_id', $shipment->id)->open()->exists();
    }
}
