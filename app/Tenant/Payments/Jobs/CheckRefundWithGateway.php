<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Jobs;

use App\Tenant\Payments\Actions\CheckPendingRefund;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks the gateway about a refund still on its way back, on the payments queue, in its store. Not retried: its own schedule, and the hourly sweep behind it, ask again.
 */
final class CheckRefundWithGateway implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  int|null  $checkNumber  Its place in the refund's own schedule of checks, from 0; null for a one-off check by the sweep.
     */
    public function __construct(
        public readonly int $paymentRefundId,
        public readonly ?int $checkNumber = null,
    ) {
        $this->onQueue(config()->string('payments.queue'));
    }

    public function handle(CheckPendingRefund $checkPendingRefund): void
    {
        $paymentRefund = PaymentRefund::query()->find($this->paymentRefundId);

        if ($paymentRefund !== null) {
            $checkPendingRefund->handle($paymentRefund, $this->checkNumber);
        }
    }
}
