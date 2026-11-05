<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;

/**
 * A refund a module's destination is asked to pay out: whose it is, how much, and whether it belongs to a test order.
 */
final readonly class DestinationRefund
{
    /**
     * @param  string  $refundId  The refund's public ID: the same refund always has the same one, so a module can tell it already paid it out.
     * @param  string  $orderId  The order's public ID.
     * @param  string  $customerId  The public ID of the customer it goes to.
     * @param  bool  $isTest  Whether the order is a test order: a module must never pay a test refund out as real value.
     */
    public function __construct(
        public string $refundId,
        public string $orderId,
        public string $customerId,
        public Money $amount,
        public bool $isTest,
    ) {}
}
