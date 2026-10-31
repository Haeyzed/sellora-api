<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Contracts;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;

/**
 * What an order's payments really brought in and gave back, for Orders to work out what it owes. Implemented by Tenant\Payments.
 */
interface OrderPaymentAmounts
{
    /**
     * What its succeeded payments received; in the order's currency.
     */
    public function paid(Order $order): Money;

    /**
     * What went back from those payments; in the order's currency.
     */
    public function refunded(Order $order): Money;
}
