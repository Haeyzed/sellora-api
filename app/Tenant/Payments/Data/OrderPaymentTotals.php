<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;

/**
 * What an order's payments add up to: what was paid (succeeded payments, at what they received) and what was refunded.
 */
final readonly class OrderPaymentTotals
{
    public function __construct(
        public Money $paid,
        public Money $refunded,
    ) {}
}
