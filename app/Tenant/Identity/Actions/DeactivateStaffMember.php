<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Identity\Exceptions\CannotManageOwnAccountException;
use App\Tenant\Identity\Exceptions\PermissionsExceedYourOwnException;
use App\Tenant\Identity\Exceptions\StoreOwnerProtectedException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAuthority;

/**
 * Removes someone from the team: they are signed out everywhere at once and can't sign in again.
 *
 * The account is kept, so the store's history still shows who did what, and
 * it can be reactivated. It no longer counts towards the staff limit.
 */
final readonly class DeactivateStaffMember
{
    public function __construct(
        private StaffAuthority $staffAuthority,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @throws CannotManageOwnAccountException When the actor deactivates themselves.
     * @throws StoreOwnerProtectedException When the staff member is the owner.
     * @throws PermissionsExceedYourOwnException When the staff member has permissions the actor doesn't hold.
     */
    public function handle(StaffMember $actor, StaffMember $staffMember): StaffMember
    {
        $this->staffAuthority->ensureCanManage($actor, $staffMember);

        if (! $staffMember->is_active) {
            return $staffMember;
        }

        return StaffMember::query()->getConnection()->transaction(function () use ($actor, $staffMember): StaffMember {
            $staffMember->forceFill(['is_active' => false])->save();
            $this->accessTokenIssuer->revokeAll($staffMember);

            activity('team')
                ->causedBy($actor)
                ->performedOn($staffMember)
                ->event('staff_deactivated')
                ->log("Deactivated {$staffMember->name}");

            return $staffMember;
        });
    }
}
