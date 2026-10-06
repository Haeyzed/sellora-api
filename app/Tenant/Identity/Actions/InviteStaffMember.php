<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\Models\Role;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\RoleNotAssignableException;
use App\Tenant\Identity\Exceptions\StaffInvitationAlreadyPendingException;
use App\Tenant\Identity\Exceptions\StaffMemberAlreadyExistsException;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAccountLimit;
use App\Tenant\Identity\Services\StaffAuthority;
use App\Tenant\Identity\Services\StaffInvitationLinks;

/**
 * Invites someone to join the store's team by email, with the roles they will have.
 *
 * No account exists until they accept and choose their own password. A
 * pending invitation holds a place in the plan's staff account limit.
 */
final readonly class InviteStaffMember
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private StaffAccountLimit $staffAccountLimit,
        private StaffInvitationLinks $staffInvitationLinks,
    ) {}

    /**
     * @param  string  $email  Already trimmed and lower-cased.
     * @param  list<Role>  $roles  The store's staff roles they will get.
     *
     * @throws RoleNotAssignableException When one of the roles is Owner.
     * @throws PermissionsExceedYourOwnException When the roles grant permissions the inviter doesn't hold.
     * @throws StaffMemberAlreadyExistsException When the email already belongs to a staff member.
     * @throws StaffInvitationAlreadyPendingException When the email already has a pending invitation.
     * @throws UsageLimitReachedException When the plan has no room for another staff account.
     */
    public function handle(StaffMember $inviter, string $email, ?string $name, array $roles): StaffInvitation
    {
        $this->staffAuthority->ensureCanGiveRoles($inviter, $roles);

        return StaffInvitation::query()->getConnection()->transaction(function () use ($inviter, $email, $name, $roles): StaffInvitation {
            $this->staffAccountLimit->ensureRoomForOneMore();

            if (StaffMember::query()->where('email', $email)->exists()) {
                throw new StaffMemberAlreadyExistsException;
            }

            if (StaffInvitation::query()->pending()->where('email', $email)->exists()) {
                throw new StaffInvitationAlreadyPendingException;
            }

            $staffInvitation = new StaffInvitation(['email' => $email, 'name' => $name]);
            $staffInvitation->invitedBy()->associate($inviter);
            $this->staffInvitationLinks->sendNew($staffInvitation, $inviter);
            $staffInvitation->roles()->sync(array_map(static fn (Role $role): int => $role->id, $roles));

            activity('team')
                ->causedBy($inviter)
                ->performedOn($staffInvitation)
                ->event('staff_invited')
                ->withProperties(['email' => $email, 'roles' => array_map(static fn (Role $role): string => $role->name, $roles)])
                ->log("Invited {$email} to the team");

            return $staffInvitation->load('roles');
        });
    }
}
