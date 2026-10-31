<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Jobs;

use App\Tenant\Orders\Actions\IssueInvoice;
use App\Tenant\Orders\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        if ($order !== null && $issueInvoice->handle($order) === null) {
            Log::warning('An order paid in full no longer is by the time its invoice came to be issued; none was.', ['order' => $order->public_id, 'payment_status' => $order->refresh()->payment_status->value]);
        }
    }
}
