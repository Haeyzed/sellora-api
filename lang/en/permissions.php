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
    'inventory' => [
        'view' => 'See stock levels, locations and the history of every stock change.',
        'adjust' => 'Receive stock, correct stock counts, and change how each variant\'s stock is handled.',
        'costs' => 'See and change what each unit costs the store. Costs are internal and never shown to customers.',
    ],

    'customers' => [
        'view' => 'See customers, their profiles, addresses, staff notes and groups, including deactivated and trashed accounts.',
        'manage' => 'Change customers\' profiles, deactivate, reactivate, trash and restore accounts, add or delete notes, and manage customer groups.',
        'tax_exemption' => 'Make a customer tax-exempt, or end it, after checking their tax ID.',
        'privacy' => 'Download all of a customer\'s personal data, or erase it for good, at the customer\'s request.',
    ],

    'promotions' => [
        'view' => 'See promotions, their codes and how often they were used, and preview what a basket would get.',
        'manage' => 'Create, change, activate, deactivate and archive promotions, and add, generate or change their codes.',
    ],

    'shipping' => [
        'view' => 'See shipping zones, methods and their rates, and preview what a basket would pay for shipping.',
        'manage' => 'Create, change and delete shipping zones, and create, change and archive shipping methods and their rates.',
    ],

    'orders' => [
        'view' => 'See orders, their lines, payments, history and shipments.',
        'manage' => 'Cancel orders, add notes and tags, and correct buyers\' contact details and shipping addresses.',
        'fulfil' => 'Create shipments, add tracking, and mark what has left, is ready for pickup or has arrived.',
    ],

    'invoices' => [
        'view' => 'See invoices and credit notes, with the buyer\'s details on them, and download their PDFs.',
    ],

    'payments' => [
        'view' => 'See payments, refunds and how payment gateways are connected.',
        'confirm' => 'Confirm that a bank transfer arrived or cash on delivery was collected, and ask a gateway again about a payment.',
        'refund' => 'Refund payments, through the gateway or as recorded by hand.',
        'manage_gateways' => 'Connect, disconnect and set up payment gateways and payment methods.',
    ],

    'pricing' => [
        'view' => 'See tax classes, tax rates, which class each variant is in, and the store\'s currencies and exchange rates.',
        'manage' => 'Change tax classes, tax rates, variants\' tax classes, the store\'s currencies, exchange rates and fixed prices.',
    ],

    'settings' => [
        'view' => 'See the store\'s settings: name, country, currency, languages, tax mode, units and contact details.',
        'manage' => 'Change the store\'s settings. Requiring two-factor authentication for staff stays with the owner.',
    ],

    'roles' => [
        'view' => 'See the store\'s roles and what each one allows.',
        'manage' => 'Create, rename and delete roles, and choose what each one allows.',
    ],

    'delivery' => [
        'view' => 'See the store\'s drivers and deliveries.',
        'manage' => 'Give shipments to drivers, and move, retry, take back or cancel their deliveries.',
    ],

    'drivers' => [
        'manage' => 'Add, change, deactivate and erase drivers, and give them codes to choose a PIN with.',
    ],

];
