<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Services;

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Tenant\Promotions\Contracts\OrderHistory;
use App\Tenant\Promotions\Data\DiscountBasket;
use App\Tenant\Promotions\Data\DiscountLine;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Data\LineCategories;
use App\Tenant\Promotions\Enums\PromotionTargetList;
use App\Tenant\Promotions\Enums\PromotionType;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCurrencyAmount;

/**
 * Works out what one promotion gives a basket, or why it gives nothing.
 *
 * Amounts set in the base currency are used as they are in the base currency,
 * as the exact amount set for the basket's currency when there is one, and
 * otherwise converted at the basket's rate, rounded half-up. A percentage or
 * fixed amount is worked out once on the eligible lines together and spread
 * across them by largest remainder, weighted by what each still costs, so the
 * parts add up exactly; buy X get Y discounts the cheapest qualifying units.
 *
 * Reasons: usage_limit_reached, email_needed (a guest must give an email for a
 * limit per customer), customer_limit_reached, first_order_only,
 * customer_group, sale_items_excluded (only items on sale would qualify),
 * no_eligible_items, minimum_not_met.
 */
final readonly class PromotionEvaluator
{
    public function __construct(
        private OrderHistory $orderHistory,
        private PromotionUsage $promotionUsage,
    ) {}

    /**
     * @param  Promotion  $promotion  With its targets, groups and currency amounts loaded.
     * @return DiscountProposal|string The discount, or why there is none.
     */
    public function evaluate(Promotion $promotion, DiscountBasket $basket, LineCategories $categories, bool $isCode): DiscountProposal|string
    {
        $reason = $this->buyerReason($promotion, $basket);

        if ($reason !== null) {
            return $reason;
        }

        $eligible = $this->linesIn($promotion, $basket->lines, $categories, PromotionTargetList::Include);
        $onSale = array_filter($eligible, static fn (DiscountLine $line): bool => $line->isOnSale);

        if (! $promotion->includes_sale_items) {
            $eligible = array_values(array_filter($eligible, static fn (DiscountLine $line): bool => ! $line->isOnSale));
        }

        if ($eligible === []) {
            return $onSale !== [] ? 'sale_items_excluded' : 'no_eligible_items';
        }

        if (! $this->meetsMinimums($promotion, $eligible, $basket)) {
            return 'minimum_not_met';
        }

        $lineDiscounts = [];
        $shippingDiscount = null;

        switch ($promotion->type) {
            case PromotionType::Percentage:
                $total = Money::sum($basket->currency, array_map($basket->remainingOf(...), $eligible));
                $lineDiscounts = $this->spread($this->capped($promotion, $total->multiplyBy(self::fraction($promotion)), $basket), $eligible, $basket);
                break;
            case PromotionType::FixedAmount:
                $total = Money::sum($basket->currency, array_map($basket->remainingOf(...), $eligible));
                $amount = $this->inBasketCurrency($promotion, 'discount', $basket) ?? Money::zero($basket->currency);
                $lineDiscounts = $this->spread($amount->isGreaterThan($total) ? $total : $amount, $eligible, $basket);
                break;
            case PromotionType::FreeShipping:
                $shipping = $basket->shipping ?? Money::zero($basket->currency);
                $shippingDiscount = $this->capped($promotion, $shipping, $basket);
                break;
            case PromotionType::BuyXGetY:
                $lineDiscounts = $this->buyXGetY($promotion, $basket, $categories, $eligible);

                if ($lineDiscounts === []) {
                    return 'minimum_not_met';
                }

                break;
        }

        return new DiscountProposal(
            source: 'promotion',
            reference: $promotion->public_id,
            rank: $promotion->id,
            label: (string) $promotion->getTranslation('title', app()->getLocale()),
            class: $promotion->discountClass(),
            combinesWith: $promotion->combinableClasses(),
            lineDiscounts: array_filter($lineDiscounts, static fn (Money $amount): bool => ! $amount->isZero()),
            shippingDiscount: $shippingDiscount,
            isCode: $isCode,
            recalculate: function (DiscountBasket $other) use ($promotion, $categories, $isCode): ?DiscountProposal {
                $proposal = $this->evaluate($promotion, $other, $categories, $isCode);

                return $proposal instanceof DiscountProposal ? $proposal : null;
            },
        );
    }

    private function buyerReason(Promotion $promotion, DiscountBasket $basket): ?string
    {
        $buyer = $basket->buyer;

        // Advisory: ReservePromotionUses checks the limits again, under lock, at checkout.
        if ($this->promotionUsage->isUsedUp($promotion, except: $basket->holder, at: $basket->at)) {
            return 'usage_limit_reached';
        }

        if ($promotion->usage_limit_per_customer !== null && $buyer->customer === null && $buyer->email === null) {
            return 'email_needed';
        }

        if ($this->promotionUsage->hasBuyerUsedUp($promotion, $buyer, $basket->holder, $basket->at)) {
            return 'customer_limit_reached';
        }

        if ($promotion->first_order_only && (($buyer->customer === null && $buyer->email === null) || $this->orderHistory->hasOrdered($buyer->customer, $buyer->email))) {
            return 'first_order_only';
        }

        if ($promotion->customerGroups->isNotEmpty() && array_intersect($promotion->customerGroups->modelKeys(), $buyer->groupIds) === []) {
            return 'customer_group';
        }

        return null;
    }

    /**
     * The lines on a list (with nothing on it: every line), less those excluded.
     *
     * @param  list<DiscountLine>  $lines
     * @return list<DiscountLine>
     */
    private function linesIn(Promotion $promotion, array $lines, LineCategories $categories, PromotionTargetList $list): array
    {
        $listed = $this->listed($promotion, $list);
        $excluded = $this->listed($promotion, PromotionTargetList::Exclude);
        $isEmpty = $listed['products'] === [] && $listed['variants'] === [] && $listed['categories'] === [];

        return array_values(array_filter($lines, fn (DiscountLine $line): bool => ($isEmpty || $this->matches($line, $listed, $categories))
            && ! $this->matches($line, $excluded, $categories)));
    }

    /**
     * @return array{products: list<int>, variants: list<int>, categories: list<int>}
     */
    private function listed(Promotion $promotion, PromotionTargetList $list): array
    {
        $ids = [];

        foreach (['products', 'variants', 'categories'] as $kind) {
            $ids[$kind] = array_values($promotion->{$kind}->filter(static fn ($target): bool => $target->getRelationValue('pivot')->list === $list->value)->modelKeys());
        }

        return $ids;
    }

    /**
     * @param  array{products: list<int>, variants: list<int>, categories: list<int>}  $listed
     */
    private function matches(DiscountLine $line, array $listed, LineCategories $categories): bool
    {
        return in_array($line->variant->product_id, $listed['products'], true)
            || in_array($line->variant->id, $listed['variants'], true)
            || array_intersect($categories->forLine($line->key), $listed['categories']) !== [];
    }

    /**
     * @param  list<DiscountLine>  $eligible
     */
    private function meetsMinimums(Promotion $promotion, array $eligible, DiscountBasket $basket): bool
    {
        if ($promotion->min_quantity !== null && array_sum(array_map(static fn (DiscountLine $line): int => $line->quantity, $eligible)) < $promotion->min_quantity) {
            return false;
        }

        $minimum = $this->inBasketCurrency($promotion, 'min_subtotal', $basket);

        return $minimum === null || ! Money::sum($basket->currency, array_map(static fn (DiscountLine $line): Money => $line->subtotal(), $eligible))->isLessThan($minimum);
    }

    /**
     * Buy X get Y: the cheapest units of the "get" items, get_quantity for each buy_quantity units of the "include" items bought besides them, as many times as there are enough (up to max_applications).
     *
     * @param  list<DiscountLine>  $eligible  The items to buy.
     * @return array<string, Money> By line key; empty when it doesn't apply.
     */
    private function buyXGetY(Promotion $promotion, DiscountBasket $basket, LineCategories $categories, array $eligible): array
    {
        $hasGetList = $this->listed($promotion, PromotionTargetList::Get) !== ['products' => [], 'variants' => [], 'categories' => []];
        $getLines = $hasGetList ? $this->linesIn($promotion, $basket->lines, $categories, PromotionTargetList::Get) : $eligible;

        if (! $promotion->includes_sale_items) {
            $getLines = array_values(array_filter($getLines, static fn (DiscountLine $line): bool => ! $line->isOnSale));
        }

        $buyKeys = array_map(static fn (DiscountLine $line): string => $line->key, $eligible);
        $units = [];

        foreach ($getLines as $line) {
            for ($i = 0; $i < $line->quantity; $i++) {
                $units[] = $line;
            }
        }

        // Cheapest first; equal prices in basket order.
        usort($units, static fn (DiscountLine $a, DiscountLine $b): int => $a->unitPrice->minorAmount() <=> $b->unitPrice->minorAmount());
        $buyUnits = array_sum(array_map(static fn (DiscountLine $line): int => $line->quantity, $eligible));
        $buy = (int) $promotion->buy_quantity;
        $get = (int) $promotion->get_quantity;
        $applications = 0;
        $maximum = $promotion->max_applications ?? PHP_INT_MAX;

        for ($n = 1; $n <= $maximum && $n * $get <= count($units); $n++) {
            $gets = array_slice($units, 0, $n * $get);
            $buyUnitsUsedAsGets = count(array_filter($gets, static fn (DiscountLine $line): bool => in_array($line->key, $buyKeys, true)));

            if ($buyUnits - $buyUnitsUsedAsGets < $n * $buy) {
                break;
            }

            $applications = $n;
        }

        $discounts = [];

        foreach (array_slice($units, 0, $applications * $get) as $unit) {
            $off = $unit->unitPrice->multiplyBy(self::fraction($promotion));
            $discounts[$unit->key] = isset($discounts[$unit->key]) ? $discounts[$unit->key]->add($off) : $off;
        }

        if ($discounts === []) {
            return [];
        }

        $capped = $this->capped($promotion, Money::sum($basket->currency, array_values($discounts)), $basket);

        return $capped->isEqualTo(Money::sum($basket->currency, array_values($discounts)))
            ? $discounts
            : $capped->allocate(array_map(static fn (Money $amount): int => $amount->minorAmount(), $discounts));
    }

    /**
     * Spreads an amount across the lines by largest remainder, weighted by what each still costs.
     *
     * @param  list<DiscountLine>  $lines
     * @return array<string, Money>
     */
    private function spread(Money $amount, array $lines, DiscountBasket $basket): array
    {
        $weights = [];

        foreach ($lines as $line) {
            $weights[$line->key] = $basket->remainingOf($line)->minorAmount();
        }

        if ($weights === [] || array_sum($weights) === 0) {
            return [];
        }

        return $amount->allocate($weights);
    }

    private function capped(Promotion $promotion, Money $amount, DiscountBasket $basket): Money
    {
        $cap = $this->inBasketCurrency($promotion, 'max_discount', $basket);

        return $cap !== null && $amount->isGreaterThan($cap) ? $cap : $amount;
    }

    /**
     * One of the promotion's amounts in the basket's currency: as set for the base currency, the exact amount set for this currency, or converted at the basket's rate.
     *
     * @param  'discount'|'max_discount'|'min_subtotal'  $field
     */
    private function inBasketCurrency(Promotion $promotion, string $field, DiscountBasket $basket): ?Money
    {
        $base = $promotion->{$field};

        if (! $base instanceof Money) {
            return null;
        }

        if ($base->currency() === $basket->currency) {
            return $base;
        }

        $exact = $promotion->currencyAmounts->first(static fn (PromotionCurrencyAmount $amounts): bool => $amounts->currency_code === $basket->currency)?->{$field};

        return $exact instanceof Money ? $exact : $base->convertTo($basket->currency, $basket->exchangeRate->rate);
    }

    private static function fraction(Promotion $promotion): Decimal
    {
        return ($promotion->percent ?? Decimal::of(0))->divideBy(100, 6);
    }
}
