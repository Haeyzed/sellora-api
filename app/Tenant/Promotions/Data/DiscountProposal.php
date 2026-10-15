<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Shared\Money\Money;
use App\Tenant\Promotions\Enums\DiscountClass;
use Closure;

/**
 * A discount a source offers for a basket: how much it takes off each line or the delivery, and what it combines with.
 *
 * The engine may ask for it again for part of the basket (when another
 * product discount took some lines) or after other discounts (an order
 * discount works on what remains), through $recalculate.
 */
final readonly class DiscountProposal
{
    /**
     * @param  string  $source  Which source offers it, such as "promotion".
     * @param  string  $reference  What it is in that source, such as the promotion's public ID.
     * @param  int  $rank  Breaks ties between equal discounts: the lower (older) wins.
     * @param  string  $label  What the buyer sees, in the request's language.
     * @param  list<DiscountClass>  $combinesWith
     * @param  array<string, Money>  $lineDiscounts  By line key; only lines it discounts.
     * @param  Money|null  $shippingDiscount  For a shipping discount.
     * @param  bool  $isCode  It came from the code the buyer entered.
     * @param  Closure(DiscountBasket): (DiscountProposal|null)  $recalculate  The same discount for another basket; null when it no longer applies.
     */
    public function __construct(
        public string $source,
        public string $reference,
        public int $rank,
        public string $label,
        public DiscountClass $class,
        public array $combinesWith,
        public array $lineDiscounts,
        public ?Money $shippingDiscount,
        public bool $isCode,
        public Closure $recalculate,
    ) {}

    public function total(string $currency): Money
    {
        return Money::sum($currency, [...array_values($this->lineDiscounts), ...($this->shippingDiscount === null ? [] : [$this->shippingDiscount])]);
    }

    /**
     * Whether it may apply together with the other: each must combine with the other's class.
     */
    public function combinesWith(self $other): bool
    {
        return in_array($other->class, $this->combinesWith, true) && in_array($this->class, $other->combinesWith, true);
    }
}
