<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * What staff can be allowed to do with the store's promotions.
 */
enum PromotionPermission: string
{
    /** See promotions, their codes and how often they were used, and preview what a basket would get. */
    case PromotionsView = 'promotions.view';

    /** Create, change, activate, deactivate and archive promotions, and add, generate or change their codes. */
    case PromotionsManage = 'promotions.manage';
}
