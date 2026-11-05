<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\PlatformPermissionCatalogue;
use App\Shared\Auth\Contracts\GrantablePermission;
use App\Shared\Auth\PermissionTexts;
use App\Tenant\Identity\StaffPermissionCatalogue;
use App\Tenant\Payments\Enums\PaymentPermission;
use Tests\Support\Permissions\UntranslatedPermission;

/*
 * Every permission says which group dashboards list it under and whether
 * granting it needs extra care (section 10). Groups are the domain a
 * permission belongs to, which its name doesn't always say.
 */

it('lists every store permission under its domain\'s group', function (): void {
    $groups = [];

    foreach (app(StaffPermissionCatalogue::class)->permissions() as $permission) {
        $groups[$permission->value] = $permission->group();
    }

    expect($groups)->toBe([
        'catalog.manage' => 'catalog', 'catalog.view' => 'catalog',
        'customers.manage' => 'customers', 'customers.privacy' => 'customers', 'customers.tax_exemption' => 'customers', 'customers.view' => 'customers',
        'delivery.cash.manage' => 'delivery', 'delivery.manage' => 'delivery', 'delivery.view' => 'delivery', 'drivers.manage' => 'delivery',
        'inventory.adjust' => 'inventory', 'inventory.costs' => 'inventory', 'inventory.view' => 'inventory',
        'invoices.view' => 'orders', 'orders.fulfil' => 'orders', 'orders.manage' => 'orders', 'orders.view' => 'orders',
        'payments.confirm' => 'payments', 'payments.manage_gateways' => 'payments', 'payments.refund' => 'payments', 'payments.view' => 'payments',
        'pricing.manage' => 'pricing', 'pricing.view' => 'pricing',
        'promotions.manage' => 'promotions', 'promotions.view' => 'promotions',
        'returns.exchange' => 'returns', 'returns.manage' => 'returns', 'returns.view' => 'returns',
        'roles.manage' => 'team', 'roles.view' => 'team',
        'settings.manage' => 'settings', 'settings.view' => 'settings',
        'shipping.manage' => 'shipping', 'shipping.view' => 'shipping',
        'staff.invite' => 'team', 'staff.manage' => 'team', 'staff.view' => 'team',
    ]);
});

it('lists every platform permission under its group', function (): void {
    $groups = [];

    foreach (app(PlatformPermissionCatalogue::class)->permissions() as $permission) {
        $groups[$permission->value] = $permission->group();
    }

    expect($groups)->toBe([
        'legal_documents.manage' => 'legal', 'stores.export' => 'stores', 'stores.grant' => 'stores', 'stores.manage' => 'stores', 'stores.view' => 'stores',
    ]);
});

it('marks as sensitive exactly the permissions that move money, show personal data or costs, or change who may do what', function (): void {
    $sensitive = static fn (array $permissions): array => array_values(array_map(
        static fn (GrantablePermission $permission): string => (string) $permission->value,
        array_filter($permissions, static fn (GrantablePermission $permission): bool => $permission->isSensitive()),
    ));

    expect($sensitive(app(StaffPermissionCatalogue::class)->permissions()))->toBe([
        'customers.privacy', 'customers.tax_exemption', 'delivery.cash.manage', 'drivers.manage', 'inventory.costs', 'invoices.view',
        'payments.manage_gateways', 'payments.refund', 'returns.exchange', 'roles.manage', 'settings.manage', 'staff.invite', 'staff.manage',
    ])
        ->and($sensitive(app(PlatformPermissionCatalogue::class)->permissions()))->toBe(['stores.export', 'stores.grant', 'stores.manage']);
});

it('finds a permission by its name, and nothing for a name no code defines', function (): void {
    expect(app(StaffPermissionCatalogue::class)->find('payments.refund'))->toBe(PaymentPermission::PaymentsRefund)
        ->and(app(StaffPermissionCatalogue::class)->find('payments.everything'))->toBeNull()
        ->and(app(PlatformPermissionCatalogue::class)->find('stores.export'))->toBe(PlatformPermission::StoresExport)
        ->and(app(PlatformPermissionCatalogue::class)->find('payments.refund'))->toBeNull();
});

it('fails loudly rather than show the key when a permission has no text', function (Closure $read): void {
    expect(fn () => $read(app(PermissionTexts::class), UntranslatedPermission::Untranslated))->toThrow(LogicException::class);
})->with([
    // The test takes a Closure, so Pest passes each one as it is rather than calling it.
    'label' => [static fn (PermissionTexts $texts, GrantablePermission $permission): string => $texts->label($permission)],
    'description' => [static fn (PermissionTexts $texts, GrantablePermission $permission): string => $texts->description($permission)],
    'group' => [static fn (PermissionTexts $texts, GrantablePermission $permission): string => $texts->groupLabel($permission)],
]);
