<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Shared\Money\Money;

/**
 * The discounts a basket gets: which apply, what each line and the delivery get off, and what happened to the code entered.
 */
final readonly class DiscountOutcome
{
    /**
     * @param  list<DiscountProposal>  $applied  In the order they apply: product discounts, then the order discount, then shipping.
     * @param  array<string, Money>  $lineDiscounts  The total off each line, by key; never more than the line.
     * @param  string|null  $codeReason  Why the code entered doesn't apply; null when it applies or none was entered.
     */
    public function __construct(
        public string $currency,
        public array $applied,
        public array $lineDiscounts,
        public Money $shippingDiscount,
        public ?string $code = null,
        public ?string $codeReason = null,
    ) {}

    public function total(): Money
    {
        return Money::sum($this->currency, [...array_values($this->lineDiscounts), $this->shippingDiscount]);
    }

    public function codeApplies(): bool
    {
        return $this->code !== null && $this->codeReason === null;
    }
}
