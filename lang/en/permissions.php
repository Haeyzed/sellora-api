<?php

declare(strict_types=1);

/*
| The short label and one-line description of each permission a store's roles
| and the platform's roles can grant, shown when choosing what a role may do.
| Keyed by permission name; the key itself is never shown (section 10).
*/

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Manage legal documents',
            'description' => 'Draft, edit and publish the legal documents merchants accept, such as the terms of service.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'View stores',
            'description' => 'See every store, its status, plan, feature grants and limit overrides.',
        ],
        'manage' => [
            'label' => 'Manage stores',
            'description' => 'Suspend, reactivate, close and restore stores, and retry setting up a store whose setup failed.',
        ],
        'grant' => [
            'label' => 'Grant features and limits',
            'description' => 'Give stores modules and integrations outside their plan, and change their usage limits.',
        ],
        'export' => [
            'label' => 'Export store data',
            'description' => 'Export all of a store\'s data, including every customer\'s personal data.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'View team',
            'description' => 'See the team: staff members, their roles and pending invitations.',
        ],
        'invite' => [
            'label' => 'Invite staff',
            'description' => 'Invite people to join the team, and resend or cancel invitations.',
        ],
        'manage' => [
            'label' => 'Manage staff',
            'description' => 'Change staff members\' roles, and deactivate or reactivate them.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'View catalog',
            'description' => 'See the catalog: brands, categories and products, including drafts, archived items and the trash.',
        ],
        'manage' => [
            'label' => 'Manage catalog',
            'description' => 'Create and change brands, categories and products, move them to the trash and restore them.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'View stock',
            'description' => 'See stock levels, locations and the history of every stock change.',
        ],
        'adjust' => [
            'label' => 'Adjust stock',
            'description' => 'Receive stock, correct stock counts, and change how each variant\'s stock is handled.',
        ],
        'costs' => [
            'label' => 'Unit costs',
            'description' => 'See and change what each unit costs the store. Costs are internal and never shown to customers.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'View customers',
            'description' => 'See customers, their profiles, addresses, staff notes and groups, including deactivated and trashed accounts.',
        ],
        'manage' => [
            'label' => 'Manage customers',
            'description' => 'Change customers\' profiles, deactivate, reactivate, trash and restore accounts, add or delete notes, and manage customer groups.',
        ],
        'tax_exemption' => [
            'label' => 'Tax exemptions',
            'description' => 'Make a customer tax-exempt, or end it, after checking their tax ID.',
        ],
        'privacy' => [
            'label' => 'Customer data requests',
            'description' => 'Download all of a customer\'s personal data, or erase it for good, at the customer\'s request.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'View promotions',
            'description' => 'See promotions, their codes and how often they were used, and preview what a basket would get.',
        ],
        'manage' => [
            'label' => 'Manage promotions',
            'description' => 'Create, change, activate, deactivate and archive promotions, and add, generate or change their codes.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'View shipping',
            'description' => 'See shipping zones, methods and their rates, and preview what a basket would pay for shipping.',
        ],
        'manage' => [
            'label' => 'Manage shipping',
            'description' => 'Create, change and delete shipping zones, and create, change and archive shipping methods and their rates.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'View orders',
            'description' => 'See orders, their lines, payments, history and shipments.',
        ],
        'manage' => [
            'label' => 'Manage orders',
            'description' => 'Cancel orders, add notes and tags, and correct buyers\' contact details and shipping addresses.',
        ],
        'fulfil' => [
            'label' => 'Fulfil orders',
            'description' => 'Create shipments, add tracking, and mark what has left, is ready for pickup or has arrived.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'View invoices',
            'description' => 'See invoices and credit notes, with the buyer\'s details on them, and download their PDFs.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'View payments',
            'description' => 'See payments, refunds and how payment gateways are connected.',
        ],
        'confirm' => [
            'label' => 'Confirm payments',
            'description' => 'Confirm that a bank transfer arrived or cash on delivery was collected, and ask a gateway again about a payment.',
        ],
        'refund' => [
            'label' => 'Refund payments',
            'description' => 'Refund payments, through the gateway or as recorded by hand.',
        ],
        'manage_gateways' => [
            'label' => 'Manage payment gateways',
            'description' => 'Connect, disconnect and set up payment gateways and payment methods.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'View tax and currencies',
            'description' => 'See tax classes, tax rates, which class each variant is in, and the store\'s currencies and exchange rates.',
        ],
        'manage' => [
            'label' => 'Manage tax and currencies',
            'description' => 'Change tax classes, tax rates, variants\' tax classes, the store\'s currencies, exchange rates and fixed prices.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'View settings',
            'description' => 'See the store\'s settings: name, country, currency, languages, tax mode, units and contact details.',
        ],
        'manage' => [
            'label' => 'Manage settings',
            'description' => 'Change the store\'s settings. Requiring two-factor authentication for staff stays with the owner.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'View roles',
            'description' => 'See the store\'s roles and what each one allows.',
        ],
        'manage' => [
            'label' => 'Manage roles',
            'description' => 'Create, rename and delete roles, and choose what each one allows.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'View deliveries',
            'description' => 'See the store\'s drivers and deliveries.',
        ],
        'manage' => [
            'label' => 'Manage deliveries',
            'description' => 'Give shipments to drivers, and move, retry, take back or cancel their deliveries.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Record driver cash',
                'description' => 'Record cash drivers hand over, never more than they hold.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Manage drivers',
            'description' => 'Add, change, deactivate and erase drivers, and give them codes to choose a PIN with.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'View returns',
            'description' => 'See returns, their photos and history, and the return settings.',
        ],
        'manage' => [
            'label' => 'Manage returns',
            'description' => 'Start, approve, reject, receive, inspect and cancel returns, and change the return settings.',
        ],
    ],
];
