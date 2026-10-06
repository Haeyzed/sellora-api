<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\PlatformAdminAlreadyExistsException;
use App\Landlord\Identity\Exceptions\PlatformAdminInvitationAlreadyPendingException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Identity\Services\PlatformAdminInvitationLinks;
use App\Shared\Auth\Models\Role;

/**
 * Invites someone to join Sellora's team by email, with the platform roles they will have.
 *
 * No account exists until they accept and choose their own password, so no
 * one ever holds a password someone else chose.
 */
final readonly class InvitePlatformAdmin
{
    public function __construct(private PlatformAdminInvitationLinks $platformAdminInvitationLinks) {}

    /**
     * @param  string  $email  Already trimmed and lower-cased.
     * @param  list<Role>  $roles  Platform roles they will get.
     *
     * @throws PlatformAdminAlreadyExistsException When the email already belongs to a platform admin.
     * @throws PlatformAdminInvitationAlreadyPendingException When the email already has a pending invitation.
     */
    public function handle(PlatformAdmin $inviter, string $email, ?string $name, array $roles): PlatformAdminInvitation
    {
        return PlatformAdminInvitation::query()->getConnection()->transaction(function () use ($inviter, $email, $name, $roles): PlatformAdminInvitation {
            // Held until commit, so two invitations for the same email can't both pass the checks.
            PlatformAdminInvitation::query()->getConnection()->select('select pg_advisory_xact_lock(hashtext(?))', ['platform-admin-invitation:'.$email]);

            if (PlatformAdmin::query()->where('email', $email)->exists()) {
                throw new PlatformAdminAlreadyExistsException;
            }

            if (PlatformAdminInvitation::query()->pending()->where('email', $email)->exists()) {
                throw new PlatformAdminInvitationAlreadyPendingException;
            }

            $platformAdminInvitation = new PlatformAdminInvitation(['email' => $email, 'name' => $name]);
            $platformAdminInvitation->invitedBy()->associate($inviter);
            $this->platformAdminInvitationLinks->sendNew($platformAdminInvitation, $inviter);
            $platformAdminInvitation->roles()->sync(array_map(static fn (Role $role): int => $role->id, $roles));

            activity('platform_team')
                ->causedBy($inviter)
                ->performedOn($platformAdminInvitation)
                ->event('platform_admin_invited')
                ->withProperties(['email' => $email, 'roles' => array_map(static fn (Role $role): string => $role->name, $roles)])
                ->log("Invited {$email} to the platform team");

            return $platformAdminInvitation->load('roles');
        });
    }
}
