<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\PaymentRefund;
use Carbon\CarbonImmutable;

/**
 * Each pending gateway refund's own schedule of checks, on the same waits as payments (config payments.checks.backoff_minutes), so it settles without waiting for a webhook. When the schedule runs out, the hourly sweep carries on asking.
 */
final readonly class RefundChecks
{
    public function scheduleFirst(PaymentRefund $paymentRefund): void
    {
        $this->schedule($paymentRefund, 0);
    }

    /**
     * Queues the refund's next check, if its schedule has one.
     */
    public function scheduleAfter(PaymentRefund $paymentRefund, int $checkNumber): void
    {
        $this->schedule($paymentRefund, $checkNumber + 1);
    }

    private function schedule(PaymentRefund $paymentRefund, int $checkNumber): void
    {
        /** @var list<int> $backoff */
        $backoff = config()->array('payments.checks.backoff_minutes');

        if (! isset($backoff[$checkNumber])) {
            return;
        }

        CheckRefundWithGateway::dispatch($paymentRefund->id, $checkNumber)->delay(CarbonImmutable::now()->addMinutes($backoff[$checkNumber]));
    }
}
