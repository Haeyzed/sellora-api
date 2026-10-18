<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Tenant\Orders\Contracts\PaymentCheckBeforeExpiry;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Services\OrderPayments;

/**
 * Asks the gateway once about an expiring order's gateway payment still under way. Never gated by the plan or the store's status.
 */
final readonly class GatewayCheckBeforeExpiry implements PaymentCheckBeforeExpiry
{
    public function __construct(
        private OrderPayments $orderPayments,
        private VerifyPayment $verifyPayment,
    ) {}

    public function checkBeforeExpiry(Order $order): void
    {
        $payment = $this->orderPayments->gatewayPaymentInMotion($order);

        if ($payment !== null) {
            $this->verifyPayment->handle($payment);
        }
    }
}
