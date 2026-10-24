<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\PaymentSettlement;
use Carbon\CarbonImmutable;

/**
 * Asks the gateway, server-side, where a payment stands, and applies the answer. The only way a gateway payment's status moves; a webhook or the buyer coming back only triggers it.
 *
 * Never gated by the plan or the store's status: money already in motion is
 * always looked after. A gateway that doesn't answer changes nothing but
 * when it was last asked.
 */
final readonly class VerifyPayment
{
    /** Statuses the gateway can still change: money may arrive, or arrived after all. */
    private const array VERIFIABLE = [PaymentStatus::Pending, PaymentStatus::Unresolved, PaymentStatus::Cancelled, PaymentStatus::Failed];

    public function __construct(
        private PaymentGatewayRegistry $registry,
        private PaymentSettlement $paymentSettlement,
    ) {}

    public function handle(Payment $payment): Payment
    {
        $connection = $payment->connection;

        if ($payment->method !== PaymentMethod::Gateway || ! in_array($payment->status, self::VERIFIABLE, true) || $connection === null || ! $connection->holdsCredentials()) {
            return $payment;
        }

        try {
            $result = $this->registry->get($connection->gateway)->verifyPayment($connection->gatewayCredentials(), $payment->reference, $payment->amount);
        } catch (GatewayUnavailableException) {
            $payment->forceFill(['last_checked_at' => CarbonImmutable::now(), 'checks' => $payment->checks + 1])->save();

            return $payment;
        }

        return $this->paymentSettlement->apply($payment, $result);
    }
}
