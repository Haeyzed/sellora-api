<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Tenant\Identity\Exceptions\StaffInvitationNotPendingException;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;

/**
 * Cancels an invitation, so its link stops working and it no longer holds a place in the staff limit.
 */
final readonly class RevokeStaffInvitation
{
    /**
     * @throws StaffInvitationNotPendingException When it was already accepted or cancelled.
     */
    public function handle(StaffMember $actor, StaffInvitation $staffInvitation): void
    {
        if (! $staffInvitation->isOpen()) {
            throw new StaffInvitationNotPendingException;
        }

        $staffInvitation->forceFill(['revoked_at' => CarbonImmutable::now()])->save();

        activity('team')
            ->causedBy($actor)
            ->performedOn($staffInvitation)
            ->event('staff_invitation_revoked')
            ->log("Cancelled the invitation to {$staffInvitation->email}");
    }
}
