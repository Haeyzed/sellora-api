<?php

declare(strict_types=1);

use App\Landlord\Identity\Actions\ChangePlatformAdminPermissions;
use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformRolePermissions;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Actions\ChangeStaffMemberPermissions;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffRolePermissions;
use App\Tenant\Identity\StaffPermissionCatalogue;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
 * Section 10: permissions are defined in code and synced into the central
 * database (platform) and every store database (staff), at store setup and
 * on every deploy. A permission no longer in code is removed only once no
 * role or person holds it.
 */
uses(DatabaseTruncation::class);

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @return list<string>
 */
function permissionNames(string $guard): array
{
    /** @var list<string> $names */
    $names = Permission::query()->where('guard_name', $guard)->orderBy('name')->pluck('name')->all();

    return $names;
}

/**
 * @return list<string>
 */
function platformPermissionNames(): array
{
    $names = array_map(static fn (PlatformPermission $permission): string => $permission->value, PlatformPermission::cases());
    sort($names);

    return $names;
}

it('gives a new store every staff permission defined in code when it is set up', function (): void {
    $store = createStore('synced-store');

    expect($store->run(static fn (): array => permissionNames(StaffMember::GUARD)))->toBe(app(StaffPermissionCatalogue::class)->all());
});

it('writes the code\'s permissions into the central database and every store database, and changes nothing the second time', function (): void {
    $first = createStore('first-synced-store');
    $second = createStore('second-synced-store');
    $first->run(static fn () => Permission::query()->where('name', 'catalog.manage')->delete());
    $second->run(static fn () => Permission::query()->where('name', 'settings.view')->delete());

    expect(Artisan::call('permissions:sync'))->toBe(0)
        ->and(Artisan::output())->toContain('Platform')->toContain('added '.implode(', ', platformPermissionNames()))->toContain('added catalog.manage')->toContain('added settings.view')
        ->and(permissionNames(PlatformAdmin::GUARD))->toBe(platformPermissionNames())
        ->and($first->run(static fn (): array => permissionNames(StaffMember::GUARD)))->toBe(app(StaffPermissionCatalogue::class)->all())
        ->and($second->run(static fn (): array => permissionNames(StaffMember::GUARD)))->toBe(app(StaffPermissionCatalogue::class)->all());

    expect(Artisan::call('permissions:sync'))->toBe(0)
        ->and(Artisan::output())->not->toContain('added')
        ->and(Permission::query()->count())->toBe(count(platformPermissionNames()));
});

it('removes a permission no longer in code only once no role or person holds it', function (): void {
    $store = createStore('stale-store');

    $store->run(static function (): void {
        Permission::findOrCreate('reports.unused', StaffMember::GUARD);
        Role::findOrCreate('Analyst', StaffMember::GUARD)->givePermissionTo(Permission::findOrCreate('reports.by_role', StaffMember::GUARD));
        StaffMember::factory()->create()->givePermissionTo(Permission::findOrCreate('reports.direct', StaffMember::GUARD));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });

    expect(Artisan::call('permissions:sync'))->toBe(0)
        ->and(Artisan::output())->toContain('removed reports.unused')->toContain('still held by a role or person, so kept: reports.by_role, reports.direct');

    $names = $store->run(static fn (): array => permissionNames(StaffMember::GUARD));
    expect($names)->toContain('reports.by_role')->toContain('reports.direct')->not->toContain('reports.unused');

    $store->run(static function (): void {
        Role::findByName('Analyst', StaffMember::GUARD)->revokePermissionTo('reports.by_role');
        StaffMember::query()->sole()->revokePermissionTo('reports.direct');
    });
    Artisan::call('permissions:sync');

    expect($store->run(static fn (): array => permissionNames(StaffMember::GUARD)))->toBe(app(StaffPermissionCatalogue::class)->all());
});

it('skips stores without a database, and keeps syncing the others when one store fails', function (): void {
    createStoreRecord()->update(['status' => 'provisioning']);
    $broken = createStoreRecord();
    $healthy = createStore('healthy-store');
    $healthy->run(static fn () => Permission::query()->where('name', 'catalog.view')->delete());

    expect(Artisan::call('permissions:sync'))->toBe(1)
        ->and(Artisan::output())->toContain("Store {$broken->id}")->toContain('1 stores could not be synced')
        ->and($healthy->run(static fn (): array => permissionNames(StaffMember::GUARD)))->toContain('catalog.view');
});

it('refuses loudly to grant a permission whose row was never synced, instead of silently granting nothing', function (): void {
    $store = createStore('unsynced-store');

    $store->run(static function (): void {
        Permission::query()->where('name', 'catalog.view')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate('Cataloguer', StaffMember::GUARD);
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        $staffMember = StaffMember::factory()->create();

        expect(static fn () => app(StaffRolePermissions::class)->replace($role, ['catalog.view']))->toThrow(PermissionDoesNotExist::class)
            ->and(static fn () => app(ChangeStaffMemberPermissions::class)->handle($owner, $staffMember, ['catalog.view']))->toThrow(PermissionDoesNotExist::class)
            ->and($role->permissions()->count())->toBe(0)
            ->and($staffMember->permissions()->count())->toBe(0);
    });

    // The central database has no platform permission rows until it is synced.
    $superAdmin = PlatformAdmin::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate(PlatformRole::SuperAdmin->value, PlatformAdmin::GUARD));
    $platformAdmin = PlatformAdmin::factory()->create();
    $platformRole = Role::findOrCreate('Support', PlatformAdmin::GUARD);

    expect(static fn () => app(PlatformRolePermissions::class)->replace($platformRole, [PlatformPermission::StoresView]))->toThrow(PermissionDoesNotExist::class)
        ->and(static fn () => app(ChangePlatformAdminPermissions::class)->handle($superAdmin, $platformAdmin, [PlatformPermission::StoresView]))->toThrow(PermissionDoesNotExist::class)
        ->and($platformRole->permissions()->count())->toBe(0)
        ->and($platformAdmin->permissions()->count())->toBe(0);
});
