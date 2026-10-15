<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Enums;

/**
 * How an order using the method reaches the shopper.
 */
enum ShippingFulfilment: string
{
    /** Shipped with a carrier the merchant books. */
    case Ship = 'ship';

    /** Delivered by the store's own drivers (Delivery, Step 17). */
    case OwnDelivery = 'own_delivery';

    /** Collected by the shopper at one of the store's locations. */
    case Pickup = 'pickup';

    /**
     * Whether it goes to the shopper's address, so the order needs one.
     */
    public function needsAddress(): bool
    {
        return $this !== self::Pickup;
    }
}
