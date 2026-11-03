<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Listeners;

use App\Tenant\Orders\Actions\RecordOrderMoneyChange;
use App\Tenant\Payments\Events\PaymentFailed;
use App\Tenant\Payments\Events\PaymentNeedsReview;
use App\Tenant\Payments\Events\PaymentSucceeded;
use App\Tenant\Payments\Events\RefundSucceeded;

/**
 * A payment was recorded or settled one way or the other, or part of a refund went back: the order's money changed, even when its payment status stays the same (another partial refund).
 */
final readonly class RecordMoneyChangeWhenPaymentChanges
{
    public function __construct(private RecordOrderMoneyChange $recordOrderMoneyChange) {}

    public function handle(PaymentSucceeded|PaymentFailed|PaymentNeedsReview|RefundSucceeded $event): void
    {
        $orderId = $event instanceof PaymentFailed || $event instanceof PaymentNeedsReview ? $event->payment->order_id : $event->order->id;

        $this->recordOrderMoneyChange->handle($orderId);
    }
}
