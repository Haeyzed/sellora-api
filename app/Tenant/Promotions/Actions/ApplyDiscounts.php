<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Shared\Money\Money;
use App\Tenant\Promotions\Data\DiscountBasket;
use App\Tenant\Promotions\Data\DiscountLine;
use App\Tenant\Promotions\Data\DiscountOutcome;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\DiscountSourceRegistry;
use App\Tenant\Promotions\Enums\DiscountClass;
use App\Tenant\Promotions\PromotionDiscountSource;

/**
 * Decides which discounts a basket gets, from every usable discount source, and what each line and the delivery get off (Step 11).
 *
 * At most one product discount applies per line, one order discount and one
 * shipping discount per order, and two discounts apply together only when
 * each combines with the other's class. Of the combinations allowed, the one
 * that saves the buyer the most in all wins; a tie goes to the one with the
 * oldest promotion. Within a combination product discounts come first (the
 * one worth most first; one whose lines another took is worked out again on
 * the lines still free), then the order discount on what the lines still
 * cost, then shipping. Nothing ever takes a line below zero.
 */
final readonly class ApplyDiscounts
{
    public function __construct(
        private DiscountSourceRegistry $discountSourceRegistry,
        private PromotionDiscountSource $promotionDiscountSource,
    ) {}

    public function handle(DiscountBasket $basket): DiscountOutcome
    {
        $proposals = [];

        foreach ($this->discountSourceRegistry->usable() as $source) {
            array_push($proposals, ...$source->propose($basket));
        }

        $productProposals = $this->bestFirst($proposals, DiscountClass::Product, $basket->currency);
        $productSets = [];
        $applied = [];
        $saved = -1;

        // Every choice of order and shipping discount (or none), each with the product discounts that combine with it.
        foreach ([null, ...$this->bestFirst($proposals, DiscountClass::Order, $basket->currency)] as $order) {
            foreach ([null, ...$this->bestFirst($proposals, DiscountClass::Shipping, $basket->currency)] as $shipping) {
                $leads = array_values(array_filter([$order, $shipping]));

                if ($order !== null && $shipping !== null && ! $order->combinesWith($shipping)) {
                    continue;
                }

                $key = implode(',', array_map(static fn (DiscountProposal $lead): string => $lead->class->value.':'.(int) in_array(DiscountClass::Product, $lead->combinesWith, true), $leads));
                $productSets[$key] ??= self::productDiscounts($basket, array_values(array_filter($productProposals, static fn (DiscountProposal $proposal): bool => self::combinesWithAll($proposal, $leads))));
                $candidate = self::withLeads($basket, $productSets[$key], $order, $shipping);

                if ($candidate === null) {
                    continue;
                }

                $candidateSaved = self::saved($basket, $candidate)->minorAmount();

                if ($candidateSaved > $saved || ($candidateSaved === $saved && self::olderThan($candidate, $applied))) {
                    $applied = $candidate;
                    $saved = $candidateSaved;
                }
            }
        }

        return new DiscountOutcome($basket->currency, $applied, self::lineDiscounts($applied, $basket), self::shippingDiscount($applied, $basket), $basket->code, $this->codeReason($basket, $proposals, $applied));
    }

    /**
     * Product discounts, worth most first, one per line: one whose lines another took is worked out again on the lines still free.
     *
     * @param  list<DiscountProposal>  $productProposals  Worth most first.
     * @return list<DiscountProposal>
     */
    private static function productDiscounts(DiscountBasket $basket, array $productProposals): array
    {
        $applied = [];
        $claimed = [];

        foreach ($productProposals as $proposal) {
            if (array_intersect(array_keys($proposal->lineDiscounts), $claimed) !== []) {
                $freeKeys = array_values(array_diff(array_map(static fn (DiscountLine $line): string => $line->key, $basket->lines), $claimed));
                $proposal = ($proposal->recalculate)($basket->onlyLines($freeKeys));
            }

            if ($proposal !== null && $proposal->total($basket->currency)->isPositive() && self::combinesWithAll($proposal, $applied)) {
                $applied[] = $proposal;
                array_push($claimed, ...array_keys($proposal->lineDiscounts));
            }
        }

        return $applied;
    }

    /**
     * The product discounts with the order and shipping discount chosen, each worked out again on what the lines still cost
     * after the discounts before it; null when one of them no longer applies, as another choice covers that.
     *
     * @param  list<DiscountProposal>  $products
     * @return list<DiscountProposal>|null
     */
    private static function withLeads(DiscountBasket $basket, array $products, ?DiscountProposal $order, ?DiscountProposal $shipping): ?array
    {
        $applied = $products;

        foreach ([$order, $shipping] as $lead) {
            if ($lead === null) {
                continue;
            }

            $lead = ($lead->recalculate)($basket->withDiscountedLines(self::lineDiscounts($applied, $basket)));

            // Free shipping counts before the delivery charge is known, at nothing yet.
            if ($lead === null || ($lead->class !== DiscountClass::Shipping && ! $lead->total($basket->currency)->isPositive())) {
                return null;
            }

            $applied[] = $lead;
        }

        return $applied;
    }

    /**
     * What the discounts save the buyer in all, as they will be taken off.
     *
     * @param  list<DiscountProposal>  $applied
     */
    private static function saved(DiscountBasket $basket, array $applied): Money
    {
        return Money::sum($basket->currency, [...array_values(self::lineDiscounts($applied, $basket)), self::shippingDiscount($applied, $basket)]);
    }

    /**
     * Between two choices that save the same: the one whose oldest discounts are older wins, then the one with more.
     *
     * @param  list<DiscountProposal>  $candidate
     * @param  list<DiscountProposal>  $current
     */
    private static function olderThan(array $candidate, array $current): bool
    {
        $ranks = static function (array $applied): array {
            $ranks = array_map(static fn (DiscountProposal $proposal): int => $proposal->rank, $applied);
            sort($ranks);

            return $ranks;
        };

        $a = $ranks($candidate);
        $b = $ranks($current);

        foreach ($a as $index => $rank) {
            if (! isset($b[$index])) {
                return true;
            }

            if ($rank !== $b[$index]) {
                return $rank < $b[$index];
            }
        }

        return false;
    }

    /**
     * The total off the delivery, never more than the delivery charge.
     *
     * @param  list<DiscountProposal>  $applied
     */
    private static function shippingDiscount(array $applied, DiscountBasket $basket): Money
    {
        $shipping = Money::zero($basket->currency);

        foreach ($applied as $proposal) {
            if ($proposal->shippingDiscount !== null) {
                $shipping = $shipping->add($proposal->shippingDiscount);
            }
        }

        return $basket->shipping !== null && $shipping->isGreaterThan($basket->shipping) ? $basket->shipping : $shipping;
    }

    /**
     * The proposals of one class, worth most first; ties go to the older (lower rank).
     *
     * @param  list<DiscountProposal>  $proposals
     * @return list<DiscountProposal>
     */
    private function bestFirst(array $proposals, DiscountClass $class, string $currency): array
    {
        $ofClass = array_values(array_filter($proposals, static fn (DiscountProposal $proposal): bool => $proposal->class === $class));
        usort($ofClass, static fn (DiscountProposal $a, DiscountProposal $b): int => [$b->total($currency)->minorAmount(), $a->rank] <=> [$a->total($currency)->minorAmount(), $b->rank]);

        return $ofClass;
    }

    /**
     * @param  list<DiscountProposal>  $applied
     */
    private static function combinesWithAll(DiscountProposal $proposal, array $applied): bool
    {
        foreach ($applied as $other) {
            if (! $proposal->combinesWith($other)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The total off each line from the discounts applied, never more than the line.
     *
     * @param  list<DiscountProposal>  $applied
     * @return array<string, Money>
     */
    private static function lineDiscounts(array $applied, DiscountBasket $basket): array
    {
        $totals = [];

        foreach ($basket->lines as $line) {
            $total = Money::zero($basket->currency);

            foreach ($applied as $proposal) {
                if (isset($proposal->lineDiscounts[$line->key])) {
                    $total = $total->add($proposal->lineDiscounts[$line->key]);
                }
            }

            $totals[$line->key] = $total->isGreaterThan($line->subtotal()) ? $line->subtotal() : $total;
        }

        return $totals;
    }

    /**
     * Why the code entered doesn't apply, or null when it does (or none was entered).
     *
     * @param  list<DiscountProposal>  $proposals
     * @param  list<DiscountProposal>  $applied
     */
    private function codeReason(DiscountBasket $basket, array $proposals, array $applied): ?string
    {
        if ($basket->code === null || array_any($applied, static fn (DiscountProposal $proposal): bool => $proposal->isCode)) {
            return null;
        }

        if (array_any($proposals, static fn (DiscountProposal $proposal): bool => $proposal->isCode)) {
            return 'better_discount_applied';
        }

        return $this->promotionDiscountSource->codeReason($basket) ?? 'better_discount_applied';
    }
}
