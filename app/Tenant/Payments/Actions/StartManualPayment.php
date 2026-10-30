<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Exceptions\NothingLeftToPayException;
use App\Tenant\Payments\Exceptions\OrderNotPayableException;
use App\Tenant\Payments\Exceptions\PaymentInProgressException;
use App\Tenant\Payments\Exceptions\PaymentMethodNotAvailableException;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Services\OrderPayments;
use App\Tenant\Payments\Services\PaymentEventLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Expects what is left to pay on an order by a method paid by hand, for Checkout (Step 16) once the order is placed: a pending payment staff confirm when the money arrives. Starting it again returns the same one.
 *
 * Only for a method the store offers, and only with an order placed for it:
 * cash on delivery for an order paid on delivery, a bank transfer for any
 * other. Never for a test order: money paid by hand is always real.
 */
final readonly class StartManualPayment
{
    public function __construct(
        private FindManualPaymentMethods $findManualPaymentMethods,
        private OrderPayments $orderPayments,
        private PaymentEventLog $paymentEventLog,
    ) {}

    /**
     * @throws PaymentMethodNotAvailableException
     * @throws OrderNotPayableException
     * @throws NothingLeftToPayException
     * @throws PaymentInProgressException
     */
    public function handle(Order $order, PaymentMethod $method): Payment
    {
        $this->ensureAvailableFor($order, $method);

        return DB::transaction(function () use ($order, $method): Payment {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->awaitsPayment() || $locked->payment_due_at->isPast()) {
                throw new OrderNotPayableException;
            }

            $inMotion = Payment::query()->where('order_id', $locked->id)->whereIn('status', PaymentStatus::inMotion())->orderByDesc('id')->first();

            if ($inMotion !== null) {
                return $inMotion->method === $method ? $inMotion : throw new PaymentInProgressException;
            }

            $amount = $this->orderPayments->balance($locked);

            // Even if the order's payment status is behind its payments: never a payment of nothing.
            if (! $amount->isPositive()) {
                throw new NothingLeftToPayException;
            }

            $payment = new Payment;
            $payment->forceFill([
                'order_id' => $locked->id,
                'method' => $method,
                'is_test' => false,
                'status' => PaymentStatus::Pending,
                'amount' => $amount,
                'reference' => 'pay_'.Str::lower((string) Str::ulid()),
            ])->save();
            $this->paymentEventLog->record($payment, PaymentEventType::Started, ['method' => $method->value]);

            return $payment;
        });
    }

    /**
     * @throws PaymentMethodNotAvailableException
     */
    private function ensureAvailableFor(Order $order, PaymentMethod $method): void
    {
        $isAvailable = $method->isManual()
            && $this->findManualPaymentMethods->find($method)->enabled
            && ! $order->is_test
            && $order->pay_on_delivery === ($method === PaymentMethod::CashOnDelivery);

        if (! $isAvailable) {
            throw new PaymentMethodNotAvailableException;
        }
    }
}
