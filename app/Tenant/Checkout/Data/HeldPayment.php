<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Shared\Money\Money;

/**
 * What a payment method held for an order at placement.
 */
final readonly class HeldPayment
{
    /**
     * @param  Money  $amount  What it covers of the order; zero for a method that holds nothing of its own (core's).
     * @param  string|null  $reference  What the method calls its hold, to commit or release it later.
     */
    public function __construct(
        public Money $amount,
        public ?string $reference = null,
    ) {}
}
