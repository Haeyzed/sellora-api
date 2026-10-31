<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Money\Money;
use App\Tenant\Orders\Contracts\OrderPaymentAmounts;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Services\OrderPayments;

/**
 * What an order's payments really brought in and gave back, as OrderPayments adds them up.
 */
final readonly class PaymentsOrderAmounts implements OrderPaymentAmounts
{
    public function __construct(private OrderPayments $orderPayments) {}

    public function paid(Order $order): Money
    {
        return $this->orderPayments->totals($order)->paid;
    }

    public function refunded(Order $order): Money
    {
        return $this->orderPayments->totals($order)->refunded;
    }
}
