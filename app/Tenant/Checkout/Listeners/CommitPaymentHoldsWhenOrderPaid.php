<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Listeners;

use App\Tenant\Checkout\Services\PaymentHolds;
use App\Tenant\Orders\Events\OrderPaid;

/**
 * Once an order is paid, what payment methods held for it at placement (such as a gift card's balance) is made final.
 */
final readonly class CommitPaymentHoldsWhenOrderPaid
{
    public function __construct(private PaymentHolds $paymentHolds) {}

    public function handle(OrderPaid $event): void
    {
        $this->paymentHolds->commit($event->order);
    }
}
