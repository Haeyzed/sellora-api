<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Enums\SettingsPermission;

/**
 * Who may see and change the store's messaging settings: the store settings permissions, as they are part of the store's settings.
 */
final class MessagingSettingsPolicy
{
    public function view(StaffMember $actor): bool
    {
        return $actor->can(SettingsPermission::SettingsView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(SettingsPermission::SettingsManage->value);
    }
}
