<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Exceptions\StaffInvitationNotPendingException;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAccountLimit;
use App\Tenant\Identity\Services\StaffAuthority;
use App\Tenant\Identity\Services\StaffInvitationLinks;

/**
 * Emails a new link for an invitation that hasn't been accepted or cancelled, for example after it expired. The old link stops working.
 */
final readonly class ResendStaffInvitation
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private StaffAccountLimit $staffAccountLimit,
        private StaffInvitationLinks $staffInvitationLinks,
    ) {}

    /**
     * @throws StaffInvitationNotPendingException When it was already accepted or cancelled.
     * @throws RoleNotAssignableException When one of its roles is Owner.
     * @throws PermissionsExceedYourOwnException When its roles grant permissions the sender doesn't hold.
     * @throws UsageLimitReachedException When it expired and the plan has no room for another staff account.
     */
    public function handle(StaffMember $sender, StaffInvitation $staffInvitation): StaffInvitation
    {
        if (! $staffInvitation->isOpen()) {
            throw new StaffInvitationNotPendingException;
        }

        $this->staffAuthority->ensureCanGiveRoles($sender, $staffInvitation->roles);

        return StaffInvitation::query()->getConnection()->transaction(function () use ($sender, $staffInvitation): StaffInvitation {
            $this->staffAccountLimit->ensureRoomForOneMore(replacing: $staffInvitation);
            $this->staffInvitationLinks->sendNew($staffInvitation, $sender);

            activity('team')
                ->causedBy($sender)
                ->performedOn($staffInvitation)
                ->event('staff_invitation_resent')
                ->log("Resent the invitation to {$staffInvitation->email}");

            return $staffInvitation;
        });
    }
}
