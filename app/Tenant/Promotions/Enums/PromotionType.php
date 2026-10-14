<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * What a promotion takes off.
 */
enum PromotionType: string
{
    /** A percentage off the eligible lines (applies_to "products") or the order (applies_to "order"), optionally capped. */
    case Percentage = 'percentage';

    /** A fixed sum off the eligible lines or the order, once per order, never more than they cost. */
    case FixedAmount = 'fixed_amount';

    /** The delivery charge, optionally only up to a cap. */
    case FreeShipping = 'free_shipping';

    /** Buy buy_quantity items, get get_quantity items at percent off (100 makes them free); the cheapest qualifying items are the ones discounted. */
    case BuyXGetY = 'buy_x_get_y';
}
