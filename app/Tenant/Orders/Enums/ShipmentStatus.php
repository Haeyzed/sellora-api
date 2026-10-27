<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

use App\Tenant\Shipping\Enums\ShippingFulfilment;

/**
 * Where a shipment is. Shipped with a carrier or the store's own drivers: pending → shipped → delivered. A pickup: pending → ready_for_pickup → delivered (collected). One that hasn't left can be cancelled. One of the store's own deliveries that comes back undelivered is returned (Step 17).
 */
enum ShipmentStatus: string
{
    /** Being packed: nothing has left yet. */
    case Pending = 'pending';

    /** A pickup waiting at the store for its buyer. */
    case ReadyForPickup = 'ready_for_pickup';

    /** Handed to the carrier or driver; its stock has left the shelf. */
    case Shipped = 'shipped';

    /** Arrived, or collected; final. */
    case Delivered = 'delivered';

    /** Never left; what it held can ship again. Final. */
    case Cancelled = 'cancelled';

    /** One of the store's own deliveries that came back undelivered; what it held is back on the shelf for the order. Final. */
    case Returned = 'returned';

    /**
     * Whether a shipment of this kind may move from this status to the other.
     */
    public function canMoveTo(self $next, ShippingFulfilment $fulfilment): bool
    {
        $pickup = $fulfilment === ShippingFulfilment::Pickup;

        return match ($this) {
            self::Pending => $next === self::Cancelled || $next === ($pickup ? self::ReadyForPickup : self::Shipped),
            self::ReadyForPickup => $next === self::Delivered || $next === self::Cancelled,
            self::Shipped => $next === self::Delivered || ($next === self::Returned && $fulfilment === ShippingFulfilment::OwnDelivery),
            self::Delivered, self::Cancelled, self::Returned => false,
        };
    }

    /**
     * Whether what it holds counts as fulfilled: delivered or collected, or handed to a carrier. One of the store's own deliveries counts only once delivered, since it may come back.
     */
    public function hasLeft(ShippingFulfilment $fulfilment): bool
    {
        return $this === self::Delivered || ($this === self::Shipped && $fulfilment === ShippingFulfilment::Ship);
    }
}
