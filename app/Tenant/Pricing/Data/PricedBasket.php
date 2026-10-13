<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;

/**
 * A priced set of lines: what Cart shows, Checkout charges, and an order saves as it is, never recalculated (section 3.3, Step 14).
 *
 * Every total is the exact sum of its lines. The exchange rate, tax mode,
 * every tax charged and who calculated it travel with it, so the order keeps
 * what was applied even when rates or settings change later.
 */
final readonly class PricedBasket
{
    /**
     * @param  array<string, PricedLine>  $lines  By line key, in the request's order.
     * @param  LineTax|null  $shipping  The delivery charge's tax, when there is one.
     * @param  TaxAddress  $destination  Where tax was worked out to.
     * @param  string  $taxCalculatedBy  "rate_table", or a country plug-in's feature key.
     */
    public function __construct(
        public string $currency,
        public TaxMode $taxMode,
        public AppliedExchangeRate $exchangeRate,
        public array $lines,
        public ?LineTax $shipping,
        public TaxAddress $destination,
        public string $taxCalculatedBy,
    ) {}

    /**
     * Unit prices × quantities, before discounts.
     */
    public function subtotal(): Money
    {
        return Money::sum($this->currency, array_map(static fn (PricedLine $line): Money => $line->subtotal, array_values($this->lines)));
    }

    public function discount(): Money
    {
        return Money::sum($this->currency, array_map(static fn (PricedLine $line): Money => $line->discount, array_values($this->lines)));
    }

    /**
     * Every line's tax, and the delivery charge's.
     */
    public function tax(): Money
    {
        return Money::sum($this->currency, [
            ...array_map(static fn (PricedLine $line): Money => $line->tax->tax, array_values($this->lines)),
            ...($this->shipping === null ? [] : [$this->shipping->tax]),
        ]);
    }

    /**
     * What the customer pays: every line's gross, and the delivery charge's.
     */
    public function total(): Money
    {
        return Money::sum($this->currency, [
            ...array_map(static fn (PricedLine $line): Money => $line->total(), array_values($this->lines)),
            ...($this->shipping === null ? [] : [$this->shipping->gross]),
        ]);
    }
}
