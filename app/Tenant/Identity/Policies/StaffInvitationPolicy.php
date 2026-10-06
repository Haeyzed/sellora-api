<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Policies;

use App\Tenant\Identity\Enums\StaffPermission;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Who may see, send, resend and cancel invitations to the store's team.
 */
final class StaffInvitationPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(StaffPermission::StaffView->value);
    }

    public function create(StaffMember $actor): bool
    {
        return $actor->can(StaffPermission::StaffInvite->value);
    }

    public function update(StaffMember $actor, StaffInvitation $staffInvitation): bool
    {
        return $actor->can(StaffPermission::StaffInvite->value);
    }

    public function delete(StaffMember $actor, StaffInvitation $staffInvitation): bool
    {
        return $actor->can(StaffPermission::StaffInvite->value);
    }
}
