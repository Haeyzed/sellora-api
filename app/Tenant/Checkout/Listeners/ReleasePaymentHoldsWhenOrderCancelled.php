<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Listeners;

use App\Tenant\Checkout\Services\PaymentHolds;
use App\Tenant\Orders\Events\OrderCancelled;

/**
 * Once an order is cancelled, or expires unpaid, what payment methods still hold for it is given back. What was committed for a paid order stays: it goes back only through a refund.
 */
final readonly class ReleasePaymentHoldsWhenOrderCancelled
{
    public function __construct(private PaymentHolds $paymentHolds) {}

    public function handle(OrderCancelled $event): void
    {
        $this->paymentHolds->release($event->order);
    }
}
