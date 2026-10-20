<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Jobs;

use App\Tenant\Orders\Actions\IssueInvoice;
use App\Tenant\Orders\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Issues an order's invoice once it is paid in full, on the default queue, in its store. Safe to run twice; the hourly sweep issues any it missed.
 */
final class IssueOrderInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $orderId) {}

    public function handle(IssueInvoice $issueInvoice): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order !== null) {
            $issueInvoice->handle($order);
        }
    }
}
