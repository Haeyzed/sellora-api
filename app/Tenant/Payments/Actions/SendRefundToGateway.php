<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Data\GatewayRefundRequest;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\RefundSettlement;

/**
 * Asks the payment's gateway to give back part of a refund, with our refund reference as its idempotency key, so sending it again never refunds twice. Called outside any transaction.
 *
 * Never gated by the plan or the store's status: refunds of money already
 * taken carry on during a wind-down. A gateway that doesn't answer leaves it
 * pending; its checks ask again, and send it again if the gateway never got it.
 */
final readonly class SendRefundToGateway
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private RefundSettlement $refundSettlement,
    ) {}

    public function handle(PaymentRefund $paymentRefund): PaymentRefund
    {
        $payment = $paymentRefund->payment;
        $connection = $payment->connection;

        if ($paymentRefund->status !== RefundStatus::Pending || $connection === null || ! $connection->holdsCredentials()) {
            return $paymentRefund;
        }

        $request = new GatewayRefundRequest($paymentRefund->reference, $payment->reference, $payment->gateway_reference, $paymentRefund->amount);

        try {
            $result = $this->registry->get($connection->gateway)->refund($connection->gatewayCredentials(), $request);
        } catch (GatewayRejectedException $exception) {
            return $this->refundSettlement->refuse($paymentRefund, $exception->failureCode);
        } catch (GatewayUnavailableException) {
            $this->refundSettlement->touch($paymentRefund);

            return $paymentRefund;
        }

        return $this->refundSettlement->apply($paymentRefund, $result);
    }
}
