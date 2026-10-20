<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Services\RefundChecks;

/**
 * One check with the gateway for a refund still on its way back, from its own schedule or the hourly sweep; then its next scheduled check, while it stays pending. Never gated by the plan or the store's status.
 */
final readonly class CheckPendingRefund
{
    public function __construct(
        private VerifyRefund $verifyRefund,
        private RefundChecks $refundChecks,
    ) {}

    /**
     * @param  int|null  $checkNumber  Its place in the refund's own schedule; null for a one-off check.
     */
    public function handle(PaymentRefund $paymentRefund, ?int $checkNumber = null): PaymentRefund
    {
        if ($paymentRefund->status !== RefundStatus::Pending) {
            return $paymentRefund;
        }

        $checked = $this->verifyRefund->handle($paymentRefund);

        if ($checked->status === RefundStatus::Pending && $checkNumber !== null) {
            $this->refundChecks->scheduleAfter($checked, $checkNumber);
        }

        return $checked;
    }
}
