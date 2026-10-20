<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\RefundSettlement;

/**
 * Asks the gateway, server-side, where part of a refund stands, and applies the answer. The only way it moves after being sent; a webhook only triggers it.
 *
 * A gateway with no refund under our reference never got the request, so it
 * is sent again with the same reference. Never gated by the plan or the
 * store's status.
 */
final readonly class VerifyRefund
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private RefundSettlement $refundSettlement,
        private SendRefundToGateway $sendRefundToGateway,
    ) {}

    public function handle(PaymentRefund $paymentRefund): PaymentRefund
    {
        $connection = $paymentRefund->payment->connection;

        if ($paymentRefund->status === RefundStatus::Succeeded || $paymentRefund->recorded_by_type !== null || $connection === null || ! $connection->isConnected()) {
            return $paymentRefund;
        }

        try {
            $result = $this->registry->get($connection->gateway)->verifyRefund($connection->gatewayCredentials(), $paymentRefund->reference);
        } catch (GatewayUnavailableException) {
            $this->refundSettlement->touch($paymentRefund);

            return $paymentRefund;
        }

        if ($result->state === GatewayRefundState::NotFound) {
            return $this->sendRefundToGateway->handle($paymentRefund);
        }

        return $this->refundSettlement->apply($paymentRefund, $result);
    }
}
