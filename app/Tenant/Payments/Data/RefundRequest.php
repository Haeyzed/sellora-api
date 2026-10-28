<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;
use App\Tenant\Payments\Enums\RefundPurpose;

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
     * @param  RefundPurpose|null  $purpose  What it does to the sale; null for what the order and request imply. Only an amount on an order that was sold can be either: a price reduction, or the overpayment given back.
     * @param  Money|null  $withheld  What to keep back from the lines given back (Returns, Step 18), less than they are worth; only with lines, and only with a reason.
     */
    public function __construct(
        public ?Money $amount = null,
        public array $lines = [],
        public bool $shipping = false,
        public ?string $paymentId = null,
        public ?string $reason = null,
        public ?string $idempotencyKey = null,
        public ?RefundPurpose $purpose = null,
        public ?Money $withheld = null,
        public ?string $withheldReason = null,
    ) {}

    public function isByAmount(): bool
    {
        return $this->amount !== null;
    }
}
