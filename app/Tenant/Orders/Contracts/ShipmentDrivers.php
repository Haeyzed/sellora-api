<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Contracts;

use App\Tenant\Orders\Models\Shipment;

/**
 * Says whether one of the store's own drivers has a shipment, so staff don't mark it shipped or delivered behind the driver's back (Step 17). Implemented by Tenant\Delivery.
 *
 * Asked under the order's and the shipment's locks, which assigning a driver
 * also takes, so the answer can't change meanwhile.
 */
interface ShipmentDrivers
{
    /**
     * Whether the shipment has a delivery under way: assigned to a driver, or with one.
     */
    public function hasDelivery(Shipment $shipment): bool;
}
