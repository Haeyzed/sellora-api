<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Policies;

use App\Tenant\Customers\Enums\CustomerPermission;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Who among the staff may see and manage the store's customer accounts. Customers reach only their own account, through their own routes, never through this.
 */
final class CustomerPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(CustomerPermission::CustomersView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(CustomerPermission::CustomersManage->value);
    }

    public function manageTaxExemption(StaffMember $actor): bool
    {
        return $actor->can(CustomerPermission::CustomersTaxExemption->value);
    }

    public function handlePrivacyRequests(StaffMember $actor): bool
    {
        return $actor->can(CustomerPermission::CustomersPrivacy->value);
    }
}
