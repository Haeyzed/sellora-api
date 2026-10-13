<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Enums\PricingPermission;

/**
 * Who may see and change the store's tax and currency setup. Asked for every pricing screen, through the TaxClass model.
 */
final class TaxClassPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(PricingPermission::PricingView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(PricingPermission::PricingManage->value);
    }
}
