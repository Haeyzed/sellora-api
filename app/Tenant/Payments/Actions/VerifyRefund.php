<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Data\GatewayRefundRequest;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Enums\RefundReviewReason;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\GatewayConnectionAttention;
use App\Tenant\Payments\Services\RefundSettlement;

/**
 * Asks the gateway, server-side, where part of a refund stands, and applies the answer. The only way it moves after being sent; a webhook only triggers it.
 *
 * A part never sent is sent. One the gateway acknowledged is asked about by
 * the gateway's own ID for it. One whose sending went unanswered is looked
 * for among the payment's refunds: found once, its ID is kept from then on;
 * found more than once, or unclear, or if the gateway can't be asked, staff
 * review it and it is never sent again (a gateway refusing the store's keys
 * also asks the merchant to reconnect it). Only when the gateway, asked in full,
 * twice has none, a while apart, is it sent again, and only once
 * (RefundSettlement::mayResendAfterNotFound). Never gated by the plan or the
 * store's status.
 */
final readonly class VerifyRefund
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private RefundSettlement $refundSettlement,
        private SendRefundToGateway $sendRefundToGateway,
        private GatewayConnectionAttention $attention,
    ) {}

    public function handle(PaymentRefund $paymentRefund): PaymentRefund
    {
        $payment = $paymentRefund->payment;
        $connection = $payment->connection;

        if ($paymentRefund->status === RefundStatus::Succeeded || $paymentRefund->recorded_by_type !== null || $connection === null || ! $connection->holdsCredentials()) {
            return $paymentRefund;
        }

        if ($paymentRefund->send_attempts === 0) {
            return $this->sendRefundToGateway->handle($paymentRefund);
        }

        $request = new GatewayRefundRequest($paymentRefund->reference, $payment->reference, $payment->gateway_reference, $paymentRefund->amount);

        try {
            $result = $this->registry->get($connection->gateway)->verifyRefund($connection->gatewayCredentials(), $request, $paymentRefund->gateway_reference);
        } catch (GatewayUnavailableException $exception) {
            if ($exception->keysRefused) {
                $this->attention->keysRefused($connection);
            }

            return $this->cannotAsk($paymentRefund);
        }

        // Only a refund the gateway never acknowledged can be missing; one it gave an ID for stays pending.
        if ($result->state === GatewayRefundState::NotFound && $paymentRefund->gateway_reference === null) {
            return $this->refundSettlement->mayResendAfterNotFound($paymentRefund) ? $this->sendRefundToGateway->resend($paymentRefund->refresh()) : $paymentRefund->refresh();
        }

        return $this->refundSettlement->apply($paymentRefund, $result);
    }

    /**
     * A refund the gateway acknowledged is asked about again later. One it never acknowledged can't be told apart from one never made, so staff look.
     */
    private function cannotAsk(PaymentRefund $paymentRefund): PaymentRefund
    {
        if ($paymentRefund->gateway_reference === null) {
            return $this->refundSettlement->sendToReview($paymentRefund, RefundReviewReason::LookupFailed);
        }

        $this->refundSettlement->touch($paymentRefund);

        return $paymentRefund;
    }
}
