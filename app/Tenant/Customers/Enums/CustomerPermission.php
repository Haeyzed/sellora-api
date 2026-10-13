<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * What staff can be allowed to do with the store's customer accounts.
 */
enum CustomerPermission: string
{
    /** See customers, their profiles, addresses and staff notes, including deactivated and trashed accounts. */
    case CustomersView = 'customers.view';

    /** Change customers' profiles, deactivate, reactivate, trash and restore accounts, and add or delete notes. */
    case CustomersManage = 'customers.manage';
}
