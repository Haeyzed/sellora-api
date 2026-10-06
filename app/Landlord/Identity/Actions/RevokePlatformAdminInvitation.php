<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\PlatformAdminInvitationNotPendingException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use Carbon\CarbonImmutable;

/**
 * Cancels an invitation; its link stops working at once.
 */
final readonly class RevokePlatformAdminInvitation
{
    /**
     * @throws PlatformAdminInvitationNotPendingException When it was already accepted or cancelled.
     */
    public function handle(PlatformAdmin $actor, PlatformAdminInvitation $platformAdminInvitation): void
    {
        PlatformAdminInvitation::query()->getConnection()->transaction(static function () use ($actor, $platformAdminInvitation): void {
            $locked = PlatformAdminInvitation::query()->whereKey($platformAdminInvitation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new PlatformAdminInvitationNotPendingException;
            }

            $locked->forceFill(['revoked_at' => CarbonImmutable::now()])->save();

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('platform_admin_invitation_revoked')
                ->log("Cancelled the invitation for {$locked->email}");
        });
    }
}
