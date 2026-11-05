<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Enums;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * What staff can be allowed to do with the store's team. Other domains and modules add their own permissions to the catalogue as they are built.
 */
enum StaffPermission: string implements GrantablePermission
{
    /** See the team: staff members, their roles and pending invitations. */
    case StaffView = 'staff.view';

    /** Invite people to join the team, and resend or cancel invitations. */
    case StaffInvite = 'staff.invite';

    /** Change staff members' roles, and deactivate or reactivate them. */
    case StaffManage = 'staff.manage';

    /** See the store's roles and what each one allows. */
    case RolesView = 'roles.view';

    /** Create, rename and delete roles, and choose what each one allows. */
    case RolesManage = 'roles.manage';

    public function group(): string
    {
        return 'team';
    }

    public function isSensitive(): bool
    {
        return in_array($this, [self::StaffInvite, self::StaffManage, self::RolesManage], true);
    }
}
