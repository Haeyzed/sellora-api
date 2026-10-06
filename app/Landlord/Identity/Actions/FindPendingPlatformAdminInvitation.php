<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\InvalidPlatformAdminInvitationException;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Identity\Services\PlatformAdminInvitationLinks;

/**
 * Looks up the invitation behind an emailed link, so the admin app can show who it is for before they choose a password.
 */
final readonly class FindPendingPlatformAdminInvitation
{
    /**
     * @throws InvalidPlatformAdminInvitationException When the link is unknown, expired, cancelled or already used.
     */
    public function handle(string $token): PlatformAdminInvitation
    {
        $platformAdminInvitation = PlatformAdminInvitation::query()
            ->where('token_hash', PlatformAdminInvitationLinks::hash($token))
            ->with(['roles', 'invitedBy'])
            ->first();

        if ($platformAdminInvitation === null || ! $platformAdminInvitation->isPending()) {
            throw new InvalidPlatformAdminInvitationException;
        }

        return $platformAdminInvitation;
    }
}
