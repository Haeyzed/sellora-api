<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Models\Payment;
use Carbon\CarbonImmutable;

/**
 * Each pending gateway payment's own schedule of checks with its gateway, so it settles without waiting for a webhook: one after each wait in config payments.checks.backoff_minutes, counted from the previous check.
 *
 * When the schedule runs out, the hourly sweep carries on, and gives up on
 * the payment once it has been pending for give_up_after_hours.
 */
final readonly class PaymentChecks
{
    public function scheduleFirst(Payment $payment): void
    {
        $this->schedule($payment, 0);
    }

    /**
     * Queues the payment's next check, if its schedule has one.
     */
    public function scheduleAfter(Payment $payment, int $checkNumber): void
    {
        $this->schedule($payment, $checkNumber + 1);
    }

    private function schedule(Payment $payment, int $checkNumber): void
    {
        /** @var list<int> $backoff */
        $backoff = config()->array('payments.checks.backoff_minutes');

        if (! isset($backoff[$checkNumber])) {
            return;
        }

        CheckPaymentWithGateway::dispatch($payment->id, $checkNumber)->delay(CarbonImmutable::now()->addMinutes($backoff[$checkNumber]));
    }
}
