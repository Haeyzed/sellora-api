<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Tenant\Identity\Exceptions\InvalidStaffInvitationException;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Services\StaffInvitationLinks;

/**
 * Finds the invitation behind an emailed link, so the dashboard can show who is joining before they choose a password.
 */
final readonly class FindPendingStaffInvitation
{
    /**
     * @throws InvalidStaffInvitationException When the link is unknown, expired, cancelled or already used.
     */
    public function handle(string $token): StaffInvitation
    {
        $staffInvitation = StaffInvitation::query()
            ->pending()
            ->where('token_hash', StaffInvitationLinks::hash($token))
            ->with('roles')
            ->first();

        return $staffInvitation ?? throw new InvalidStaffInvitationException;
    }
}
