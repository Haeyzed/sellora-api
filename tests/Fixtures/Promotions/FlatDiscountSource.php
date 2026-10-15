<?php

declare(strict_types=1);

namespace Tests\Fixtures\Promotions;

use App\Shared\Money\Money;
use App\Tenant\Promotions\Contracts\DiscountSource;
use App\Tenant\Promotions\Data\DiscountBasket;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Enums\DiscountClass;

/**
 * A module's discount source, as loyalty points would be: a flat order discount on the whole basket that combines with everything.
 */
final class FlatDiscountSource implements DiscountSource
{
    public static int $minorAmount = 100;

    public function propose(DiscountBasket $basket): array
    {
        $remaining = array_map($basket->remainingOf(...), $basket->lines);
        $total = Money::sum($basket->currency, $remaining);

        if (! $total->isPositive()) {
            return [];
        }

        $amount = Money::ofMinor(min(self::$minorAmount, $total->minorAmount()), $basket->currency);
        $weights = [];

        foreach ($basket->lines as $index => $line) {
            $weights[$line->key] = $remaining[$index]->minorAmount();
        }

        return [new DiscountProposal(
            source: 'loyalty',
            reference: 'points',
            rank: 0,
            label: 'Loyalty points',
            class: DiscountClass::Order,
            combinesWith: DiscountClass::cases(),
            lineDiscounts: $amount->allocate($weights),
            shippingDiscount: null,
            isCode: false,
            recalculate: fn (DiscountBasket $other): ?DiscountProposal => $this->propose($other)[0] ?? null,
        )];
    }
}
