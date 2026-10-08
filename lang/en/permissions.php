<?php

declare(strict_types=1);

/*
| Descriptions of the permissions a store's roles and the platform's roles
| can grant, shown when choosing what a role may do. Keyed by permission name.
*/

return [

    'legal_documents' => [
        'manage' => 'Draft, edit and publish the legal documents merchants accept, such as the terms of service.',
    ],

    'stores' => [
        'view' => 'See every store, its status, plan, feature grants and limit overrides.',
        'manage' => 'Suspend, reactivate, close and restore stores, and retry setting up a store whose setup failed.',
        'grant' => 'Give stores modules and integrations outside their plan, and change their usage limits.',
        'export' => 'Export all of a store\'s data, including every customer\'s personal data.',
    ],

    'staff' => [
        'view' => 'See the team: staff members, their roles and pending invitations.',
        'invite' => 'Invite people to join the team, and resend or cancel invitations.',
        'manage' => 'Change staff members\' roles, and deactivate or reactivate them.',
    ],

    'catalog' => [
        'view' => 'See the catalog: brands, categories and products, including drafts, archived items and the trash.',
        'manage' => 'Create and change brands, categories and products, move them to the trash and restore them.',
    ],

    'roles' => [
        'view' => 'See the store\'s roles and what each one allows.',
        'manage' => 'Create, rename and delete roles, and choose what each one allows.',
    ],

];
