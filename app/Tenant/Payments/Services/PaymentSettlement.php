<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\GatewayPaymentResult;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Enums\PaymentVerdict;
use App\Tenant\Payments\Enums\ReviewReason;
use App\Tenant\Payments\Events\PaymentFailed;
use App\Tenant\Payments\Events\PaymentNeedsReview;
use App\Tenant\Payments\Events\PaymentSucceeded;
use App\Tenant\Payments\Events\PaymentUnresolved;
use App\Tenant\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies what the gateway answered to a payment, under locks taken in the same order as everywhere else: the order first, then the payment. Safe to run twice and at the same moment as anything else touching the order.
 *
 * A success announces PaymentSucceeded inside the same transaction, so the
 * order's payment status moves with the payment, or neither does.
 */
final readonly class PaymentSettlement
{
    public function __construct(
        private PaymentConfirmation $paymentConfirmation,
        private OrderPayments $orderPayments,
        private PaymentEventLog $paymentEventLog,
    ) {}

    public function apply(Payment $payment, GatewayPaymentResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            [$verdict, $reason] = $this->paymentConfirmation->judge($locked, $order->is_test, $result);

            $locked->forceFill([
                'last_checked_at' => CarbonImmutable::now(),
                'checks' => $locked->checks + 1,
                'gateway_reference' => $locked->gateway_reference ?? $result->gatewayReference,
            ]);

            match ($verdict) {
                PaymentVerdict::Paid => $this->succeed($locked, $order, $result),
                PaymentVerdict::Mismatch => $this->sendToReview($locked, $result, $reason ?? ReviewReason::AmountMismatch),
                PaymentVerdict::NotPaid => $this->fail($locked, $result->failureCode),
                PaymentVerdict::StillPending => $locked->save(),
            };

            return $locked;
        });
    }

    /**
     * Marks a payment that never got anywhere as failed, such as one the gateway refused to start.
     */
    public function failToStart(Payment $payment, ?string $failureCode): Payment
    {
        return DB::transaction(function () use ($payment, $failureCode): Payment {
            Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->fail($locked, $failureCode);

            return $locked;
        });
    }

    /**
     * Gives up waiting on a payment the gateway still has no final answer for: it becomes unresolved, still in motion, and staff are told.
     */
    public function giveUp(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Pending) {
                return $locked;
            }

            $locked->forceFill(['status' => PaymentStatus::Unresolved, 'unresolved_at' => CarbonImmutable::now()])->save();
            $this->paymentEventLog->record($locked, PaymentEventType::Unresolved, ['checks' => $locked->checks]);
            Log::warning('A payment is still pending with its gateway after checking gave up; staff should look at it.', ['payment' => $locked->public_id]);
            PaymentUnresolved::dispatch($locked);

            return $locked;
        });
    }

    private function succeed(Payment $locked, Order $order, GatewayPaymentResult $result): void
    {
        if ($locked->status === PaymentStatus::Failed) {
            $this->sendToReview($locked, $result, ReviewReason::SucceededAfterFailing);

            return;
        }

        $locked->forceFill([
            'status' => PaymentStatus::Succeeded,
            'received' => $result->amount,
            'succeeded_at' => CarbonImmutable::now(),
            'card_brand' => $locked->card_brand ?? $result->cardBrand,
            'card_last4' => $locked->card_last4 ?? $result->cardLast4,
        ])->save();
        $this->paymentEventLog->record($locked, PaymentEventType::Succeeded, ['amount' => $locked->amount->minorAmount()]);

        PaymentSucceeded::dispatch($locked, $order, $this->orderPayments->totals($order));
    }

    private function sendToReview(Payment $locked, GatewayPaymentResult $result, ReviewReason $reason): void
    {
        $locked->forceFill([
            'status' => PaymentStatus::NeedsReview,
            'review_reason' => $reason,
            'received' => $locked->received ?? $result->amount,
            'needs_review_at' => CarbonImmutable::now(),
            'failed_at' => null,
        ])->save();
        $this->paymentEventLog->record($locked, PaymentEventType::NeedsReview, ['reason' => $reason->value, 'received' => $result->amount?->minorAmount(), 'received_currency' => $result->amount?->currency()]);
        // Only the public ID: staff find it from there.
        Log::warning('A payment arrived that does not match what was expected; it does not count toward its order.', ['payment' => $locked->public_id, 'reason' => $reason->value]);
        PaymentNeedsReview::dispatch($locked);
    }

    private function fail(Payment $locked, ?string $failureCode): void
    {
        if (! $locked->status->isInMotion()) {
            $locked->save();

            return;
        }

        $locked->forceFill(['status' => PaymentStatus::Failed, 'failure_code' => $failureCode, 'failed_at' => CarbonImmutable::now()])->save();
        $this->paymentEventLog->record($locked, PaymentEventType::Failed, ['code' => $failureCode]);
        PaymentFailed::dispatch($locked);
    }
}
