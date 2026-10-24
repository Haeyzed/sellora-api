<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Data\GatewayRefundRequest;
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\RefundSettlement;
use Carbon\CarbonImmutable;

/**
 * Asks the payment's gateway to give back part of a refund, with our refund reference so it can be found again. Called outside any transaction.
 *
 * Never sent blindly: not every gateway refuses the same refund twice. Each
 * send is first claimed in one conditional update, which only one caller can
 * win (send_attempts moves from what it read, and never back), so two at once
 * never both reach the gateway. A part is sent once, and again at most once
 * (resend), when the gateway twice reported having none (VerifyRefund); the
 * database refuses a third. A gateway that doesn't answer leaves it pending,
 * and its checks look for it.
 *
 * Never gated by the plan or the store's status: refunds of money already
 * taken carry on during a wind-down.
 */
final readonly class SendRefundToGateway
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private RefundSettlement $refundSettlement,
    ) {}

    /**
     * Sends it the first time; does nothing if it was ever claimed before.
     */
    public function handle(PaymentRefund $paymentRefund): PaymentRefund
    {
        return $this->send($paymentRefund, 0);
    }

    /**
     * Sends it the second and last time; does nothing unless it was sent exactly once.
     */
    public function resend(PaymentRefund $paymentRefund): PaymentRefund
    {
        return $this->send($paymentRefund, 1);
    }

    private function send(PaymentRefund $paymentRefund, int $sentBefore): PaymentRefund
    {
        $payment = $paymentRefund->payment;
        $connection = $payment->connection;

        if ($connection === null || ! $connection->holdsCredentials() || ! $this->claim($paymentRefund, $sentBefore)) {
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

    /**
     * Claims the send: wins only if it was sent exactly as often as the caller read, in one conditional update, so of two callers at once only one ever wins. Whether it may be sent at all is decided before (pending, and not in review: RefundOrder, RefundSettlement::mayResendAfterNotFound).
     */
    private function claim(PaymentRefund $paymentRefund, int $sentBefore): bool
    {
        return PaymentRefund::query()
            ->whereKey($paymentRefund->id)
            ->where('send_attempts', $sentBefore)
            ->update(['send_attempts' => $sentBefore + 1, 'not_found_at' => null, 'updated_at' => CarbonImmutable::now()]) === 1;
    }
}
