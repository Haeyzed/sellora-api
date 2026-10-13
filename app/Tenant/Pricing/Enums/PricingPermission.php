<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Enums;

/**
 * What staff can be allowed to do with the store's tax and currency setup.
 */
enum PricingPermission: string
{
    /** See tax classes, tax rates, which class each variant is in, and the store's currencies and exchange rates. */
    case PricingView = 'pricing.view';

    /** Change tax classes, tax rates, variants' tax classes, the store's currencies, exchange rates and fixed prices. */
    case PricingManage = 'pricing.manage';
}
