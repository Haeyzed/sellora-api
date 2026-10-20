<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Events\RefundSucceeded;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies what the gateway answered about part of a refund, under locks taken in the same order as everywhere else: the order, then the payment, then the refund. Safe to run twice and at the same moment as anything else touching the order.
 *
 * Fail-closed, like payments: it went back only when the gateway says so
 * with exactly the amount asked. A success without an amount stays pending
 * and is asked about again; one with another amount stays pending too, and
 * staff are told. A success announces RefundSucceeded inside the same
 * transaction, so the order's payment status moves with it.
 */
final readonly class RefundSettlement
{
    public function __construct(
        private OrderPayments $orderPayments,
        private PaymentEventLog $paymentEventLog,
    ) {}

    public function apply(PaymentRefund $paymentRefund, GatewayRefundResult $result): PaymentRefund
    {
        return DB::transaction(function () use ($paymentRefund, $result): PaymentRefund {
            [$order, $payment, $locked] = $this->lock($paymentRefund);
            $locked->forceFill([
                'last_checked_at' => CarbonImmutable::now(),
                'checks' => $locked->checks + 1,
                'gateway_reference' => $locked->gateway_reference ?? $result->gatewayRefundReference,
            ]);

            match ($result->state) {
                GatewayRefundState::Succeeded => $this->succeedIfExact($locked, $payment, $order, $result),
                GatewayRefundState::Failed => $this->fail($locked, $payment, $result->failureCode),
                GatewayRefundState::Pending, GatewayRefundState::NotFound => $locked->save(),
            };

            return $locked;
        });
    }

    /**
     * Marks part of a refund the gateway refused for good as failed.
     */
    public function refuse(PaymentRefund $paymentRefund, ?string $failureCode): PaymentRefund
    {
        return DB::transaction(function () use ($paymentRefund, $failureCode): PaymentRefund {
            [, $payment, $locked] = $this->lock($paymentRefund);
            $this->fail($locked, $payment, $failureCode);

            return $locked;
        });
    }

    /**
     * Records part of a refund staff paid back by hand (saved with who recorded it), inside the caller's transaction, which already holds the order's and the payment's locks.
     */
    public function recordByHand(PaymentRefund $paymentRefund, Payment $payment, Order $order, AccountReference $actor): void
    {
        $this->succeed($paymentRefund, $payment, $order, $actor);
    }

    /**
     * Notes when the gateway was asked, when it didn't answer.
     */
    public function touch(PaymentRefund $paymentRefund): void
    {
        $paymentRefund->forceFill(['last_checked_at' => CarbonImmutable::now(), 'checks' => $paymentRefund->checks + 1])->save();
    }

    /**
     * @return array{Order, Payment, PaymentRefund}
     */
    private function lock(PaymentRefund $paymentRefund): array
    {
        $payment = Payment::query()->whereKey($paymentRefund->payment_id)->firstOrFail();
        $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
        $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        return [$order, $payment, PaymentRefund::query()->whereKey($paymentRefund->id)->lockForUpdate()->firstOrFail()];
    }

    private function succeedIfExact(PaymentRefund $locked, Payment $payment, Order $order, GatewayRefundResult $result): void
    {
        if ($locked->status === RefundStatus::Succeeded || $result->amount === null) {
            $locked->save();

            return;
        }

        if (! $result->amount->hasSameCurrencyAs($locked->amount) || ! $result->amount->isEqualTo($locked->amount)) {
            $locked->save();
            // Only public IDs: staff find the rest from there.
            Log::warning('A gateway reports refunding another amount than was asked; the refund stays pending until staff look at it.', ['refund' => $locked->public_id, 'payment' => $payment->public_id]);

            return;
        }

        $this->succeed($locked, $payment, $order, null);
    }

    private function succeed(PaymentRefund $locked, Payment $payment, Order $order, ?AccountReference $actor): void
    {
        $locked->forceFill(['status' => RefundStatus::Succeeded, 'succeeded_at' => CarbonImmutable::now(), 'failed_at' => null, 'failure_code' => null])->save();
        $this->paymentEventLog->record($payment, PaymentEventType::Refunded, ['refund' => $locked->public_id, 'amount' => $locked->amount->minorAmount()], $actor);

        RefundSucceeded::dispatch($locked, $order, $this->orderPayments->totals($order));
    }

    private function fail(PaymentRefund $locked, Payment $payment, ?string $failureCode): void
    {
        if ($locked->status !== RefundStatus::Pending) {
            $locked->save();

            return;
        }

        $locked->forceFill(['status' => RefundStatus::Failed, 'failure_code' => $failureCode, 'failed_at' => CarbonImmutable::now()])->save();
        $this->paymentEventLog->record($payment, PaymentEventType::RefundFailed, ['refund' => $locked->public_id, 'code' => $failureCode]);
        Log::warning('A refund failed at the gateway; nothing went back.', ['refund' => $locked->public_id, 'payment' => $payment->public_id]);
        RefundFailed::dispatch($locked);
    }
}
