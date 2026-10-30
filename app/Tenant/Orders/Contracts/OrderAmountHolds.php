<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Contracts;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;

/**
 * What payment methods outside Payments hold or took for an order, such as a module's gift card applied at checkout: it counts as paid, and isn't asked for again; what they gave back since counts as refunded. Implemented by Tenant\Checkout.
 */
interface OrderAmountHolds
{
    /**
     * What they hold or took, given back since or not; in the order's currency, zero when nothing is held.
     */
    public function heldFor(Order $order): Money;

    /**
     * What of that they really took (the order was paid and they made it final), given back since or not; what they only hold isn't taken.
     */
    public function takenFor(Order $order): Money;

    /**
     * What of that they gave back once the order was cancelled; in the order's currency.
     */
    public function refundedFor(Order $order): Money;
}
