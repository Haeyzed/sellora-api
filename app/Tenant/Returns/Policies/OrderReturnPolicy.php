<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Returns\Enums\ReturnPermission;

/**
 * Which staff may see and manage the store's returns and return settings. Buyers see only their own, checked by their requests.
 */
final class OrderReturnPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(ReturnPermission::ReturnsView->value);
    }

    public function view(StaffMember $actor): bool
    {
        return $actor->can(ReturnPermission::ReturnsView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(ReturnPermission::ReturnsManage->value);
    }
}
