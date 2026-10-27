<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Listeners;

use App\Tenant\Delivery\Actions\CancelDelivery;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Orders\Events\ShipmentCancelled;

/**
 * A shipment cancelled before it left (on its own, or with its order) takes its delivery back from the driver, who hadn't picked it up yet.
 */
final readonly class CancelDeliveriesWhenShipmentCancelled
{
    public function __construct(private CancelDelivery $cancelDelivery) {}

    public function handle(ShipmentCancelled $event): void
    {
        $assigned = Delivery::query()->where('shipment_id', $event->shipment->id)->open()->get();

        foreach ($assigned as $delivery) {
            $this->cancelDelivery->ifAssigned($delivery, null);
        }
    }
}
