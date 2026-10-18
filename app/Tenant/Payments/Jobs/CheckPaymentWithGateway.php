<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Jobs;

use App\Tenant\Payments\Actions\CheckPendingPayment;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks the gateway about a payment still waiting on it, on the payments queue, in its store. Not retried: its own schedule, and the hourly sweep behind it, ask again.
 */
final class CheckPaymentWithGateway implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  int|null  $checkNumber  Its place in the payment's own schedule of checks, from 0; null for a one-off check by the sweep.
     */
    public function __construct(
        public readonly int $paymentId,
        public readonly ?int $checkNumber = null,
    ) {
        $this->onQueue(config()->string('payments.queue'));
    }

    public function handle(CheckPendingPayment $checkPendingPayment): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment !== null) {
            $checkPendingPayment->handle($payment, $this->checkNumber);
        }
    }
}
