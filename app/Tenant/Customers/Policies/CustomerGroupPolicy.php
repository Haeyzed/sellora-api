<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Policies;

use App\Tenant\Customers\Enums\CustomerPermission;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Who among the staff may see and manage customer groups: the same permissions as customers themselves.
 */
final class CustomerGroupPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(CustomerPermission::CustomersView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(CustomerPermission::CustomersManage->value);
    }
}
