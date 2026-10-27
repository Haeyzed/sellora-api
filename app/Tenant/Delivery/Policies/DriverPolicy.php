<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Policies;

use App\Tenant\Delivery\Enums\DeliveryPermission;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Which staff may see and manage the store's drivers.
 */
final class DriverPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(DeliveryPermission::DeliveryView->value);
    }

    public function view(StaffMember $actor): bool
    {
        return $actor->can(DeliveryPermission::DeliveryView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(DeliveryPermission::DriversManage->value);
    }
}
