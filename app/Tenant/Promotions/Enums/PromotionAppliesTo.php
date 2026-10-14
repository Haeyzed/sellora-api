<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * What a percentage or fixed-amount promotion is taken off.
 */
enum PromotionAppliesTo: string
{
    /** The eligible lines, as a product discount: at most one product discount per line. */
    case Products = 'products';

    /** The order, after product discounts: at most one order discount per order. */
    case Order = 'order';
}
