<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Shipping\Enums\ShippingPermission;

/**
 * Who may see and change the store's shipping setup. Asked for every shipping screen, through the ShippingZone model.
 */
final class ShippingZonePolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(ShippingPermission::ShippingView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(ShippingPermission::ShippingManage->value);
    }
}
