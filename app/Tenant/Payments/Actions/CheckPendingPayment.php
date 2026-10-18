<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Services\PaymentChecks;
use App\Tenant\Payments\Services\PaymentSettlement;
use Carbon\CarbonImmutable;

/**
 * One check with the gateway for a payment still waiting on it, from its own schedule or the hourly sweep. Never gated by the plan or the store's status.
 *
 * A payment still pending after config payments.checks.give_up_after_hours
 * becomes unresolved and staff are told; it stays in motion (money may yet
 * arrive, and its gateway stays connected) and is asked about again now and
 * then. Anything else goes on to its next scheduled check.
 */
final readonly class CheckPendingPayment
{
    public function __construct(
        private VerifyPayment $verifyPayment,
        private PaymentSettlement $paymentSettlement,
        private PaymentChecks $paymentChecks,
    ) {}

    /**
     * @param  int|null  $checkNumber  Its place in the payment's own schedule; null for a one-off check.
     */
    public function handle(Payment $payment, ?int $checkNumber = null): Payment
    {
        if (! $payment->status->isInMotion()) {
            return $payment;
        }

        $checked = $this->verifyPayment->handle($payment);

        if ($checked->status === PaymentStatus::Pending && $this->hasWaitedTooLong($checked)) {
            return $this->paymentSettlement->giveUp($checked);
        }

        if ($checked->status === PaymentStatus::Pending && $checkNumber !== null) {
            $this->paymentChecks->scheduleAfter($checked, $checkNumber);
        }

        return $checked;
    }

    private function hasWaitedTooLong(Payment $payment): bool
    {
        return $payment->created_at !== null
            && $payment->created_at->lessThanOrEqualTo(CarbonImmutable::now()->subHours(config()->integer('payments.checks.give_up_after_hours')));
    }
}
