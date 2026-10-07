<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Policies;

use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Enums\SettingsPermission;

/**
 * Who may see and change the store's settings.
 */
final class StoreSettingsPolicy
{
    public function view(StaffMember $actor): bool
    {
        return $actor->can(SettingsPermission::SettingsView->value);
    }

    public function update(StaffMember $actor): bool
    {
        return $actor->can(SettingsPermission::SettingsManage->value);
    }

    /**
     * Requiring two-factor authentication for staff is the owner's alone: no permission grants it.
     */
    public function requireStaffTwoFactor(StaffMember $actor): bool
    {
        return $actor->hasRole(StaffRole::Owner->value);
    }
}
