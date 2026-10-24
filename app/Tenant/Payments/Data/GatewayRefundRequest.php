<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;

/**
 * What a gateway needs to refund part or all of a payment.
 */
final readonly class GatewayRefundRequest
{
    /**
     * @param  string  $refundReference  Ours, unique per refund, sent with it so it can be found again.
     * @param  string  $paymentReference  Our reference of the payment being refunded.
     * @param  string|null  $gatewayPaymentReference  The gateway's own reference of that payment, when it gave one.
     */
    public function __construct(
        public string $refundReference,
        public string $paymentReference,
        public ?string $gatewayPaymentReference,
        public Money $amount,
    ) {}
}
