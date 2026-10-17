<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Listeners;

use App\Tenant\Orders\Actions\RecordOrderPayments;
use App\Tenant\Payments\Events\PaymentSucceeded;

/**
 * Moves an order's payment status when one of its payments succeeds, in the same transaction: paid in full marks it paid; less is partially paid.
 */
final readonly class MarkOrderAsPaidWhenPaymentSucceeds
{
    public function __construct(private RecordOrderPayments $recordOrderPayments) {}

    public function handle(PaymentSucceeded $event): void
    {
        $this->recordOrderPayments->handle($event->order, $event->totals->paid, $event->totals->refunded);
    }
}
