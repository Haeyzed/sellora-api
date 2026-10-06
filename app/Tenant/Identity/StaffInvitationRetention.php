<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Retention\RetentionScope;
use App\Shared\Retention\TimestampRetentionPolicy;
use App\Tenant\Identity\Models\StaffInvitation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes invitations, which hold an email address, once their link has been expired for the whole retention period, in every store.
 *
 * Accepted invitations go too: the staff member account they became stays.
 *
 * @extends TimestampRetentionPolicy<StaffInvitation>
 */
final class StaffInvitationRetention extends TimestampRetentionPolicy
{
    public function periodKey(): string
    {
        return 'staff_invitations';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    /**
     * @return Builder<StaffInvitation>
     */
    protected function query(): Builder
    {
        return StaffInvitation::query();
    }

    protected function timestampColumn(): string
    {
        return 'expires_at';
    }
}
