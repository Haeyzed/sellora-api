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

    /** Assign drivers to shipments, and move, retry, return or cancel deliveries. */
    case DeliveryManage = 'delivery.manage';

    /** Record cash drivers hand over, never more than they hold. */
    case DeliveryCashManage = 'delivery.cash.manage';

    /** Add, change, deactivate and erase drivers, and issue them PIN setup codes. */
    case DriversManage = 'drivers.manage';
}
