<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Listeners;

use App\Tenant\Orders\Actions\RecordOrderPayments;
use App\Tenant\Payments\Events\RefundSucceeded;

/**
 * Moves an order's payment status when part of a refund goes back, in the same transaction: partially refunded or refunded, and a cancelled order stops owing money once it is all given back.
 */
final readonly class RecordOrderPaymentsWhenRefundSucceeds
{
    public function __construct(private RecordOrderPayments $recordOrderPayments) {}

    public function handle(RefundSucceeded $event): void
    {
        $this->recordOrderPayments->handle($event->order, $event->totals->paid, $event->totals->refunded);
    }
}
