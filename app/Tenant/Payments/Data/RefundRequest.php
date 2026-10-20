<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;

/**
 * What staff ask to give back on an order: an amount, or lines and shipping (worked out at what the buyer paid for them, tax included), never both.
 */
final readonly class RefundRequest
{
    /**
     * @param  Money|null  $amount  An amount, in the order's currency; null when refunding lines or shipping.
     * @param  array<string, int>  $lines  Units to give back, by order line public ID.
     * @param  bool  $shipping  Whether to give back what was charged for shipping, its tax included.
     * @param  string|null  $paymentId  The public ID of the one payment to draw on; null draws on the newest succeeded payments first. A payment in review is refunded only when named here, for all it received.
     * @param  string|null  $idempotencyKey  The client's: the same key returns the same refund.
     */
    public function __construct(
        public ?Money $amount = null,
        public array $lines = [],
        public bool $shipping = false,
        public ?string $paymentId = null,
        public ?string $reason = null,
        public ?string $idempotencyKey = null,
    ) {}

    public function isByAmount(): bool
    {
        return $this->amount !== null;
    }
}
