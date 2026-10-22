<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Contracts;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;

/**
 * What payment methods outside Payments hold or took for an order, such as a module's gift card applied at checkout: it counts as paid, and isn't asked for again. Implemented by Tenant\Checkout.
 */
interface OrderAmountHolds
{
    /**
     * In the order's currency; zero when nothing is held.
     */
    public function heldFor(Order $order): Money;
}
