<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;

/**
 * What a gateway needs to start a payment on its hosted page. Only the order number and the buyer's email go to the gateway.
 */
final readonly class GatewayPaymentRequest
{
    /**
     * @param  string  $reference  Ours, unique per payment: the gateway's reference for it and its idempotency key.
     * @param  string  $returnUrl  Where the gateway sends the buyer back; arriving there is never proof of payment.
     */
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $buyerEmail,
        public string $orderNumber,
        public string $returnUrl,
    ) {}
}
