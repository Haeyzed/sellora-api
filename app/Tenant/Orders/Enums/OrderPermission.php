<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * What staff can be allowed to do with the store's orders.
 */
enum OrderPermission: string
{
    /** See orders, their history and shipments. */
    case OrdersView = 'orders.view';

    /** Cancel orders, add notes and tags, and correct contact details and addresses. */
    case OrdersManage = 'orders.manage';

    /** Create and update shipments: what is sent, tracking, and when it leaves or arrives. */
    case OrdersFulfil = 'orders.fulfil';
}
