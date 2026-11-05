<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Shared\Money\Money;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Shipping\Data\ShippingOption;

/**
 * What a replacement order comes to now (Step 18c), and how the exchange settles it: what the returned items credit, what the buyer still owes, and what goes back to them.
 *
 * total = credit + amountDue, and returnedValue = credit + difference: the
 * credit is the smaller of what the returned items are worth and what the
 * replacement costs. A quote with issues can't be placed.
 */
final readonly class ReplacementQuote
{
    /**
     * @param  DiscountedBasket|null  $basket  The lines priced with their promotions, allowances, tax and shipping; null when it has issues.
     * @param  ShippingOption|null  $shipping  The chosen way to send it, priced; null when nothing ships.
     * @param  list<string>  $issues  Why it can't be placed now, as error codes.
     * @param  string  $hash  Everything charged and settled: placing refuses a quote that came to anything else.
     */
    public function __construct(
        public ?DiscountedBasket $basket,
        public ?ShippingOption $shipping,
        public Money $returnedValue,
        public Money $total,
        public Money $credit,
        public Money $amountDue,
        public Money $difference,
        public array $issues,
        public string $hash,
    ) {}

    public function canBePlaced(): bool
    {
        return $this->issues === [] && $this->basket !== null;
    }
}
