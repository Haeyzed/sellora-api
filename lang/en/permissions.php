<?php

declare(strict_types=1);

/*
| Descriptions of the permissions a store's roles can grant, shown when a
| merchant chooses what a role may do. Keyed by permission name.
*/

return [

    'staff' => [
        'view' => 'See the team: staff members, their roles and pending invitations.',
        'invite' => 'Invite people to join the team, and resend or cancel invitations.',
        'manage' => 'Change staff members\' roles, and deactivate or reactivate them.',
    ],

    'roles' => [
        'view' => 'See the store\'s roles and what each one allows.',
        'manage' => 'Create, rename and delete roles, and choose what each one allows.',
    ],

];
