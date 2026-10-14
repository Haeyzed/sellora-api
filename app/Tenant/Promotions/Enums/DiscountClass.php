<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * The kinds of discount, applied in this order: product discounts first, then the order discount on what remains, then shipping.
 *
 * At most one product discount applies per line, one order discount and one
 * shipping discount per order. Two discounts apply together only when each
 * says it combines with the other's class.
 */
enum DiscountClass: string
{
    case Product = 'product';
    case Order = 'order';
    case Shipping = 'shipping';
}
