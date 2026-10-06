<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Policies;

use App\Tenant\Identity\Enums\StaffPermission;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Who may see and manage the store's team. The rules about whom they may manage (not themselves, not the owner, not someone more powerful) live in the Actions.
 */
final class StaffMemberPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(StaffPermission::StaffView->value);
    }

    public function view(StaffMember $actor, StaffMember $staffMember): bool
    {
        return $actor->can(StaffPermission::StaffView->value);
    }

    public function manage(StaffMember $actor, StaffMember $staffMember): bool
    {
        return $actor->can(StaffPermission::StaffManage->value);
    }
}
