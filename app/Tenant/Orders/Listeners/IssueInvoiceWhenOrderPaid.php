<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Listeners;

use App\Tenant\Orders\Events\OrderPaid;
use App\Tenant\Orders\Jobs\IssueOrderInvoice;

/**
 * Queues the invoice for an order paid in full (cash on delivery: once the cash is recorded). OrderPaid is announced only once the payment is saved.
 */
final readonly class IssueInvoiceWhenOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        IssueOrderInvoice::dispatch($event->order->id);
    }
}
