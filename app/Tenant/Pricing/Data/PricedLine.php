<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Money;

/**
 * One priced line: its unit price, what it comes to, and its tax, as an order line saves them (Step 14).
 */
final readonly class PricedLine
{
    /**
     * @param  Money  $subtotal  Unit price × quantity.
     * @param  Money  $discount  Off the line; zero when none.
     * @param  LineTax  $tax  Of subtotal − discount: its net, tax, gross, each tax charged and its outcome (taxed, zero-rated, exempt or no rate).
     */
    public function __construct(
        public string $key,
        public int $variantId,
        public int $quantity,
        public VariantPrice $price,
        public Money $subtotal,
        public Money $discount,
        public TaxClassData $taxClass,
        public LineTax $tax,
    ) {}

    /**
     * What the customer pays for the line: its gross, tax included.
     */
    public function total(): Money
    {
        return $this->tax->gross;
    }
}
