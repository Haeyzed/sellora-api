<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * What staff can be allowed to do with the store's orders.
 */
enum OrderPermission: string implements GrantablePermission
{
    /** See orders, their history and shipments. */
    case OrdersView = 'orders.view';

    /** Cancel orders, add notes and tags, and correct contact details and addresses. */
    case OrdersManage = 'orders.manage';

    /** Create and update shipments: what is sent, tracking, and when it leaves or arrives. */
    case OrdersFulfil = 'orders.fulfil';

    /** See invoices and credit notes, with the buyer's details on them, and download their PDFs. */
    case InvoicesView = 'invoices.view';

    public function group(): string
    {
        return 'orders';
    }

    public function isSensitive(): bool
    {
        return in_array($this, [self::InvoicesView], true);
    }
}
