<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Enums;

/**
 * What staff can be allowed to do with the store's team. Other domains and modules add their own permissions to the catalogue as they are built.
 */
enum StaffPermission: string
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
}
