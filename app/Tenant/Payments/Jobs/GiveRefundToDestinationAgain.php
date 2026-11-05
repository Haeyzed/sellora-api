<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Jobs;

use App\Tenant\Payments\Actions\GiveRefundToDestination;
use App\Tenant\Payments\Models\Refund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks a module's destination again to pay a refund out, after it didn't say whether it did, on the payments queue, in its store. Not retried: the next wait, and the hourly sweep behind it, ask again.
 */
final class GiveRefundToDestinationAgain implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $refundId)
    {
        $this->onQueue(config()->string('payments.queue'));
    }

    public function handle(GiveRefundToDestination $giveRefundToDestination): void
    {
        $refund = Refund::query()->find($this->refundId);

        if ($refund !== null) {
            $giveRefundToDestination->handle($refund);
        }
    }
}
