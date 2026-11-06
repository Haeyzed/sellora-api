<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Enums;

/**
 * Why a merchant shows one product beside another.
 */
enum ProductLinkType: string
{
    /** Goes well with it or is an alternative, such as the same shirt in linen. */
    case Related = 'related';

    /** A better or bigger version the shopper might choose instead. */
    case Upsell = 'upsell';

    /** Something to buy as well, such as socks with shoes, often shown in the cart. */
    case CrossSell = 'cross_sell';
}
