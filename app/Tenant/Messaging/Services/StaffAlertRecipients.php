<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Services;

use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Messaging\Enums\StaffAlertKind;

/**
 * Who receives a staff alert: every active staff member holding its kind's permission (through a role or directly), and the owner, who holds every permission. Never chosen by role name, which merchants rename.
 */
final readonly class StaffAlertRecipients
{
    /**
     * @return list<StaffMember>
     */
    public function for(StaffAlertKind $kind): array
    {
        $holders = StaffMember::permission($kind->permission())->where('is_active', true)->get();
        $owners = StaffMember::role(StaffRole::Owner->value)->where('is_active', true)->get();

        return array_values($holders->concat($owners)->unique('id')->sortBy('id')->all());
    }
}
