<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * How a basket gets a promotion.
 */
enum PromotionMethod: string
{
    /** Whenever the basket qualifies, without the customer doing anything. */
    case Automatic = 'automatic';

    /** Only with one of the promotion's codes; one code per order. */
    case Code = 'code';
}
