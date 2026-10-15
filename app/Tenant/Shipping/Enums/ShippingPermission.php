<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Enums;

/**
 * What staff can be allowed to do with the store's shipping setup.
 */
enum ShippingPermission: string
{
    /** See shipping zones, methods and their rates, and preview what a basket would pay. */
    case ShippingView = 'shipping.view';

    /** Change shipping zones, methods and their rates. */
    case ShippingManage = 'shipping.manage';
}
