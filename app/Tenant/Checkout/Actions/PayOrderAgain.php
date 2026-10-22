<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Tenant\Checkout\Exceptions\ReturnUrlRequiredException;
use App\Tenant\Checkout\PaymentMethods\GatewayPaymentMethods;
use App\Tenant\Checkout\PaymentMethods\ManualPaymentMethods;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Actions\StartPayment;
use App\Tenant\Payments\Exceptions\OrderNotPayableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotAvailableException;
use App\Tenant\Payments\Exceptions\PaymentInProgressException;
use App\Tenant\Payments\Exceptions\PaymentMethodNotAvailableException;
use App\Tenant\Payments\Models\Payment;

/**
 * Starts paying again for an order still waiting for payment (Step 16), with a gateway ("gateway:{key}") or a method paid by hand, for what is left: after a gateway refused, the buyer gave up, or they want another way. Its own transaction; a gateway is called outside it, as when the order was placed.
 *
 * The same rules as any payment: one gateway attempt at a time, a test order
 * only with a gateway in test mode, and never after the payment deadline.
 */
final readonly class PayOrderAgain
{
    public function __construct(
        private StartPayment $startPayment,
        private StartManualPayment $startManualPayment,
    ) {}

    /**
     * @param  string  $methodKey  "gateway:{key}", "bank_transfer" or "cash_on_delivery".
     * @param  string  $idempotencyKey  The client's: a retry returns the same payment.
     *
     * @throws ReturnUrlRequiredException
     * @throws PaymentGatewayNotAvailableException
     * @throws PaymentMethodNotAvailableException
     * @throws OrderNotPayableException
     * @throws PaymentInProgressException
     */
    public function handle(Order $order, string $methodKey, ?string $returnUrl, string $idempotencyKey): Payment
    {
        $gateway = GatewayPaymentMethods::gatewayOf($methodKey);

        if ($gateway !== null) {
            return $this->startPayment->handle($order, $gateway, $returnUrl ?? throw new ReturnUrlRequiredException, $idempotencyKey);
        }

        return $this->startManualPayment->handle($order, ManualPaymentMethods::methodOf($methodKey) ?? throw new PaymentMethodNotAvailableException);
    }
}
