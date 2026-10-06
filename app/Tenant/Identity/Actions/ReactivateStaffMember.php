<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Identity\Exceptions\CannotManageOwnAccountException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\StoreOwnerProtectedException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAccountLimit;
use App\Tenant\Identity\Services\StaffAuthority;

/**
 * Lets a deactivated staff member sign in again, with the roles they had.
 */
final readonly class ReactivateStaffMember
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private StaffAccountLimit $staffAccountLimit,
    ) {}

    /**
     * @throws CannotManageOwnAccountException When the actor reactivates themselves.
     * @throws StoreOwnerProtectedException When the staff member is the owner.
     * @throws PermissionsExceedYourOwnException When the staff member has permissions the actor doesn't hold.
     * @throws UsageLimitReachedException When the plan has no room for another active staff account.
     */
    public function handle(StaffMember $actor, StaffMember $staffMember): StaffMember
    {
        $this->staffAuthority->ensureCanManage($actor, $staffMember);

        if ($staffMember->is_active) {
            return $staffMember;
        }

        return StaffMember::query()->getConnection()->transaction(function () use ($actor, $staffMember): StaffMember {
            $this->staffAccountLimit->ensureRoomForOneMore();
            $staffMember->forceFill(['is_active' => true])->save();

            activity('team')
                ->causedBy($actor)
                ->performedOn($staffMember)
                ->event('staff_reactivated')
                ->log("Reactivated {$staffMember->name}");

            return $staffMember;
        });
    }
}
