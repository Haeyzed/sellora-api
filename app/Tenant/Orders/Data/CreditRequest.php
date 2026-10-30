<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

use App\Shared\Money\Money;

/**
 * What one part of a refund that reduced the sale gave back, for a credit note: Payments works it out, Orders issues the document.
 *
 * The amount is what went back from this part, tax included: its lines, its
 * shipping and any price reduction (adjustment) add up to it exactly.
 */
final readonly class CreditRequest
{
    /**
     * @param  string  $reference  The refund part's public ID: one credit note per part.
     * @param  string|null  $refundReference  The refund's public ID; null only for a sale undone without a refund.
     * @param  list<CreditedLine>  $lines
     */
    public function __construct(
        public int $orderId,
        public string $reference,
        public ?string $refundReference,
        public Money $amount,
        public array $lines,
        public Money $shipping,
        public Money $shippingTax,
        public Money $adjustment,
    ) {}
}
