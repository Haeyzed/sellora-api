<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Policies;

use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;

/**
 * What only the store's owner may do to the store as a whole: close it, or take a full export of its data.
 *
 * No permission grants these, so no role a colleague holds can either.
 */
final class StoreLifecyclePolicy
{
    public function close(StaffMember $actor): bool
    {
        return $actor->hasRole(StaffRole::Owner->value);
    }
}
