<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Contracts;

use App\Tenant\Orders\Models\Order;

/**
 * Asks the gateway one last time about an order's payment still under way, just before the order expires, so a slow method (USSD, a bank transfer through a gateway) never loses an order that was actually paid. Implemented by Tenant\Payments.
 *
 * Called outside any transaction, before the order is locked: a payment it
 * confirms pays the order, which then doesn't expire. A gateway that doesn't
 * answer changes nothing; money arriving later is owed back.
 */
interface PaymentCheckBeforeExpiry
{
    public function checkBeforeExpiry(Order $order): void;
}
