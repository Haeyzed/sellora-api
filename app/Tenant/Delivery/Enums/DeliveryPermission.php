<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Enums;

/**
 * What staff can be allowed to do with the store's own drivers and deliveries.
 */
enum DeliveryPermission: string
{
    /** See drivers and deliveries. */
    case DeliveryView = 'delivery.view';

    /** Add, change, deactivate and erase drivers, and issue them PIN setup codes. */
    case DriversManage = 'drivers.manage';
}
