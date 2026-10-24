<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\GatewayPaymentRequest;
use App\Tenant\Payments\Enums\ConnectionStatus;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Exceptions\OrderNotPayableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotAvailableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Exceptions\PaymentInProgressException;
use App\Tenant\Payments\Exceptions\PaymentRefusedByGatewayException;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\PaymentGatewayResolver;
use App\Tenant\Payments\Services\GatewayConnectionAttention;
use App\Tenant\Payments\Services\OrderPayments;
use App\Tenant\Payments\Services\PaymentChecks;
use App\Tenant\Payments\Services\PaymentEventLog;
use App\Tenant\Payments\Services\PaymentSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starts a payment of what is left to pay on an order, on a gateway's hosted page, for Checkout (Step 16) to send the buyer to. Only this is gated by the plan: everything after it carries on whatever the plan.
 *
 * One attempt at a time: while an earlier gateway payment on the order may
 * still go through, it is returned again (same gateway) or a new one is
 * refused. The payment is saved before the gateway is called, outside the
 * transaction, with our reference as the gateway's idempotency key; when the
 * gateway doesn't answer, the payment stays pending and is asked about later,
 * on its own schedule of checks, which starts before the gateway is called.
 * A gateway refusing the store's keys is treated the same, and the merchant
 * is asked to reconnect it.
 */
final readonly class StartPayment
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private PaymentGatewayResolver $resolver,
        private OrderPayments $orderPayments,
        private PaymentEventLog $paymentEventLog,
        private PaymentSettlement $paymentSettlement,
        private PaymentChecks $paymentChecks,
        private GatewayConnectionAttention $attention,
    ) {}

    /**
     * @param  string  $returnUrl  Where the gateway sends the buyer back; arriving there only triggers a check with the gateway.
     * @param  string|null  $idempotencyKey  The client's: the same key returns the same payment.
     *
     * @throws PaymentGatewayNotAvailableException
     * @throws OrderNotPayableException
     * @throws PaymentInProgressException
     * @throws PaymentRefusedByGatewayException
     * @throws PaymentGatewayUnreachableException When the gateway didn't answer; the payment stays pending and is checked later.
     */
    public function handle(Order $order, string $gatewayKey, string $returnUrl, ?string $idempotencyKey = null): Payment
    {
        $connection = $this->connectionFor($order, $gatewayKey);
        $payment = $this->createPayment($order, $connection, $idempotencyKey);

        if ($payment->redirect_url !== null || $payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        // Before calling the gateway, so a payment is checked on even if this request never finishes.
        $this->paymentChecks->scheduleFirst($payment);

        return $this->startWithGateway($payment, $order, $connection, $returnUrl);
    }

    /**
     * @throws PaymentGatewayNotAvailableException
     */
    private function connectionFor(Order $order, string $gatewayKey): PaymentGatewayConnection
    {
        $connection = PaymentGatewayConnection::query()->where('gateway', $gatewayKey)->where('status', ConnectionStatus::Connected->value)->first();

        if ($connection === null || ! $this->resolver->takes($connection, $order->currency, $order->tax_country_code, $order->is_test)) {
            throw new PaymentGatewayNotAvailableException;
        }

        return $connection;
    }

    /**
     * @throws OrderNotPayableException
     * @throws PaymentInProgressException
     */
    private function createPayment(Order $order, PaymentGatewayConnection $connection, ?string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($order, $connection, $idempotencyKey): Payment {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $earlier = $idempotencyKey === null ? null : Payment::query()->where('order_id', $locked->id)->where('idempotency_key', $idempotencyKey)->first();

            if ($earlier !== null) {
                return $earlier;
            }

            if (! $locked->awaitsPayment() || $locked->payment_due_at->isPast()) {
                throw new OrderNotPayableException;
            }

            $inMotion = $this->orderPayments->gatewayPaymentInMotion($locked);

            if ($inMotion !== null) {
                return $inMotion->connection_id === $connection->id && $inMotion->redirect_url !== null ? $inMotion : throw new PaymentInProgressException;
            }

            $payment = new Payment;
            $payment->forceFill([
                'order_id' => $locked->id,
                'method' => PaymentMethod::Gateway,
                'gateway' => $connection->gateway,
                'connection_id' => $connection->id,
                'is_test' => $connection->mode?->isTest() ?? false,
                'status' => PaymentStatus::Pending,
                'amount' => $this->orderPayments->balance($locked),
                'reference' => 'pay_'.Str::lower((string) Str::ulid()),
                'idempotency_key' => $idempotencyKey,
            ])->save();
            $this->paymentEventLog->record($payment, PaymentEventType::Started, ['gateway' => $connection->gateway]);

            return $payment;
        });
    }

    /**
     * @throws PaymentRefusedByGatewayException
     * @throws PaymentGatewayUnreachableException
     */
    private function startWithGateway(Payment $payment, Order $order, PaymentGatewayConnection $connection, string $returnUrl): Payment
    {
        $request = new GatewayPaymentRequest($payment->reference, $payment->amount, (string) $order->currentContact()->email, $order->displayNumber(), $returnUrl);

        try {
            $started = $this->registry->get($connection->gateway)->startPayment($connection->gatewayCredentials(), $request);
        } catch (GatewayRejectedException $exception) {
            $this->paymentSettlement->failToStart($payment, $exception->failureCode);

            throw new PaymentRefusedByGatewayException;
        } catch (GatewayUnavailableException $exception) {
            if ($exception->keysRefused) {
                $this->attention->keysRefused($connection);
            }

            throw new PaymentGatewayUnreachableException;
        }

        $payment->forceFill(['redirect_url' => $started->redirectUrl, 'gateway_reference' => $started->gatewayReference])->save();

        return $payment;
    }
}
