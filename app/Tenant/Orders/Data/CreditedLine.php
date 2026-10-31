<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

use App\Shared\Money\Money;

/**
 * Units of an order line credited, at what the buyer paid for them, tax included. The tax in it comes from what is left of the invoice's (InvoiceCreditsLeft).
 */
final readonly class CreditedLine
{
    public function __construct(
        public int $orderLineId,
        public int $quantity,
        public Money $amount,
    ) {}
}
