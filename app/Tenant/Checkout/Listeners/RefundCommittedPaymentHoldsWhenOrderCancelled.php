<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Listeners;

use App\Tenant\Checkout\Services\PaymentHolds;
use App\Tenant\Orders\Events\OrderCancelled;

/**
 * Once an order cancelled after it was paid is saved, each payment method that took money for it at checkout (such as a gift card) is asked to give it back. Only after the cancellation commits (OrderCancelled is dispatched after commit): a method may call out, and must never hold up or undo the cancellation.
 */
final readonly class RefundCommittedPaymentHoldsWhenOrderCancelled
{
    public function __construct(private PaymentHolds $paymentHolds) {}

    public function handle(OrderCancelled $event): void
    {
        $this->paymentHolds->refund($event->order);
    }
}
