<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * Which list of a promotion a product, variant or category is on.
 */
enum PromotionTargetList: string
{
    /** Eligible (for buy X get Y: the items to buy). With nothing included, every item is. */
    case Include = 'include';

    /** Never eligible, even when also included. */
    case Exclude = 'exclude';

    /** Buy X get Y only: the items that get the discount. With none, the same items as the ones to buy. */
    case Get = 'get';
}
