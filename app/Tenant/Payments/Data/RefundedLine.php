<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\OrderLine;

/**
 * Units of an order line to give back, and what the buyer paid for them, tax included.
 */
final readonly class RefundedLine
{
    public function __construct(
        public OrderLine $line,
        public int $quantity,
        public Money $amount,
        public Money $tax,
    ) {}
}
