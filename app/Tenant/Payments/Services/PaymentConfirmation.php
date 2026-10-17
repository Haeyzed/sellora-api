<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Payments\Data\GatewayPaymentResult;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\PaymentVerdict;
use App\Tenant\Payments\Enums\ReviewReason;
use App\Tenant\Payments\Models\Payment;

/**
 * The fail-closed rule, in one place (section 14): a payment is paid only when the gateway, asked server-side, reports success with an amount, and that amount is exactly what was expected, in the same currency, in the order's mode.
 *
 * Anything missing or unclear is not paid: it stays pending and is asked
 * again. Anything different is never accepted: the money goes to review and
 * staff refund it.
 */
final readonly class PaymentConfirmation
{
    /**
     * @return array{PaymentVerdict, ReviewReason|null}
     */
    public function judge(Payment $payment, bool $orderIsTest, GatewayPaymentResult $result): array
    {
        return match ($result->state) {
            GatewayPaymentState::Succeeded => $this->judgeSuccess($payment, $orderIsTest, $result),
            GatewayPaymentState::Failed => [PaymentVerdict::NotPaid, null],
            GatewayPaymentState::Pending, GatewayPaymentState::Unknown => [PaymentVerdict::StillPending, null],
        };
    }

    /**
     * @return array{PaymentVerdict, ReviewReason|null}
     */
    private function judgeSuccess(Payment $payment, bool $orderIsTest, GatewayPaymentResult $result): array
    {
        if ($result->amount === null) {
            return [PaymentVerdict::StillPending, null];
        }

        if (! $result->amount->hasSameCurrencyAs($payment->amount)) {
            return [PaymentVerdict::Mismatch, ReviewReason::CurrencyMismatch];
        }

        if (! $result->amount->isEqualTo($payment->amount)) {
            return [PaymentVerdict::Mismatch, ReviewReason::AmountMismatch];
        }

        $reportedTest = $result->mode?->isTest() ?? $payment->is_test;

        if ($reportedTest !== $payment->is_test || $payment->is_test !== $orderIsTest) {
            return [PaymentVerdict::Mismatch, ReviewReason::ModeMismatch];
        }

        return [PaymentVerdict::Paid, null];
    }
}
