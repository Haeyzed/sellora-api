<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Shared\Money\Money;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use Carbon\CarbonImmutable;

/**
 * What discount sources work out their discounts from: the lines, the delivery charge, the buyer, the code entered, and the discounts already applied.
 *
 * Amounts are in the basket's currency, at the rate the checkout uses, so a
 * base-currency amount converts to the same figure from cart to order.
 */
final readonly class DiscountBasket
{
    /**
     * @param  list<DiscountLine>  $lines
     * @param  Money|null  $shipping  The delivery charge before discounts; null before it is known.
     * @param  string|null  $code  The code the buyer entered, already accepted (FindPromotionCode); null for none.
     * @param  array<string, Money>  $discountedLines  Discounts already taken off each line, by key: order and shipping discounts are worked out after product ones.
     */
    public function __construct(
        public string $currency,
        public AppliedExchangeRate $exchangeRate,
        public array $lines,
        public ?Money $shipping,
        public DiscountBuyer $buyer,
        public ?string $code,
        public CarbonImmutable $at,
        public array $discountedLines = [],
    ) {}

    /**
     * What a line still costs after the discounts already taken off it.
     */
    public function remainingOf(DiscountLine $line): Money
    {
        return $line->subtotal()->subtract($this->discountedLines[$line->key] ?? Money::zero($this->currency));
    }

    /**
     * The same basket with only the lines whose keys are given.
     *
     * @param  list<string>  $keys
     */
    public function onlyLines(array $keys): self
    {
        return new self($this->currency, $this->exchangeRate, array_values(array_filter($this->lines, static fn (DiscountLine $line): bool => in_array($line->key, $keys, true))), $this->shipping, $this->buyer, $this->code, $this->at, $this->discountedLines);
    }

    /**
     * The same basket with these discounts already taken off its lines.
     *
     * @param  array<string, Money>  $discountedLines
     */
    public function withDiscountedLines(array $discountedLines): self
    {
        return new self($this->currency, $this->exchangeRate, $this->lines, $this->shipping, $this->buyer, $this->code, $this->at, $discountedLines);
    }
}
