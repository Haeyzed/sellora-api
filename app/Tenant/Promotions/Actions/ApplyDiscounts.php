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
 * Product discounts come first, then the order discount on what the lines
 * still cost, then shipping. At most one product discount applies per line,
 * one order discount and one shipping discount per order, and two discounts
 * apply together only when each combines with the other's class. Within each
 * class the discount worth most to the buyer is taken first (ties: the older);
 * a product discount whose lines another took is worked out again on the lines
 * still free. Nothing ever takes a line below zero.
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

        $applied = [];
        $claimed = [];

        foreach ($this->bestFirst($proposals, DiscountClass::Product, $basket->currency) as $proposal) {
            if (array_intersect(array_keys($proposal->lineDiscounts), $claimed) !== []) {
                $freeKeys = array_values(array_diff(array_map(static fn (DiscountLine $line): string => $line->key, $basket->lines), $claimed));
                $proposal = ($proposal->recalculate)($basket->onlyLines($freeKeys));
            }

            if ($proposal !== null && $proposal->total($basket->currency)->isPositive() && self::combinesWithAll($proposal, $applied)) {
                $applied[] = $proposal;
                array_push($claimed, ...array_keys($proposal->lineDiscounts));
            }
        }

        foreach ([DiscountClass::Order, DiscountClass::Shipping] as $class) {
            // Worked out again on what the lines still cost after the discounts already applied.
            $after = $basket->withDiscountedLines(self::lineDiscounts($applied, $basket));
            $candidates = array_values(array_filter(array_map(
                static fn (DiscountProposal $proposal): ?DiscountProposal => ($proposal->recalculate)($after),
                $this->bestFirst($proposals, $class, $basket->currency),
            )));

            foreach ($this->bestFirst($candidates, $class, $basket->currency) as $proposal) {
                $counts = $class === DiscountClass::Shipping || $proposal->total($basket->currency)->isPositive();

                if ($counts && self::combinesWithAll($proposal, $applied)) {
                    $applied[] = $proposal;

                    break;
                }
            }
        }

        $shipping = Money::zero($basket->currency);

        foreach ($applied as $proposal) {
            if ($proposal->shippingDiscount !== null) {
                $shipping = $shipping->add($proposal->shippingDiscount);
            }
        }

        if ($basket->shipping !== null && $shipping->isGreaterThan($basket->shipping)) {
            $shipping = $basket->shipping;
        }

        return new DiscountOutcome($basket->currency, $applied, self::lineDiscounts($applied, $basket), $shipping, $basket->code, $this->codeReason($basket, $proposals, $applied));
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
            return 'not_combinable';
        }

        return $this->promotionDiscountSource->codeReason($basket) ?? 'not_combinable';
    }
}
