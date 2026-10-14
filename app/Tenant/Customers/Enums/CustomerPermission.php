<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * What staff can be allowed to do with the store's customer accounts.
 */
enum CustomerPermission: string
{
    /** See customers, their profiles, addresses, staff notes and groups, including deactivated and trashed accounts. */
    case CustomersView = 'customers.view';

    /** Change customers' profiles, deactivate, reactivate, trash and restore accounts, add or delete notes, and manage customer groups. */
    case CustomersManage = 'customers.manage';

    /** Make a customer tax-exempt, or end it, after checking their tax ID. */
    case CustomersTaxExemption = 'customers.tax_exemption';

    /** Download all of a customer's personal data, or erase it, at the customer's request. */
    case CustomersPrivacy = 'customers.privacy';
}
