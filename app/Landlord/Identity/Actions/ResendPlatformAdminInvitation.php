<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\PlatformAdminAlreadyExistsException;
use App\Landlord\Identity\Exceptions\PlatformAdminInvitationNotPendingException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Identity\Services\PlatformAdminInvitationLinks;

/**
 * Emails a new link for an invitation that hasn't been accepted or cancelled, with a fresh expiry. The previous link stops working.
 */
final readonly class ResendPlatformAdminInvitation
{
    public function __construct(private PlatformAdminInvitationLinks $platformAdminInvitationLinks) {}

    /**
     * @throws PlatformAdminInvitationNotPendingException When it was accepted or cancelled.
     * @throws PlatformAdminAlreadyExistsException When the email became a platform admin's in the meantime.
     */
    public function handle(PlatformAdmin $actor, PlatformAdminInvitation $platformAdminInvitation): PlatformAdminInvitation
    {
        return PlatformAdminInvitation::query()->getConnection()->transaction(function () use ($actor, $platformAdminInvitation): PlatformAdminInvitation {
            $locked = PlatformAdminInvitation::query()->whereKey($platformAdminInvitation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new PlatformAdminInvitationNotPendingException;
            }

            if (PlatformAdmin::query()->where('email', $locked->email)->exists()) {
                throw new PlatformAdminAlreadyExistsException;
            }

            $this->platformAdminInvitationLinks->sendNew($locked, $actor);

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('platform_admin_invitation_resent')
                ->log("Resent the invitation for {$locked->email}");

            return $locked->load('roles');
        });
    }
}
