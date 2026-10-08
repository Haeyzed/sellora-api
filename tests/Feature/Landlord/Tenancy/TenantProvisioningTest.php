<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
 * PostgreSQL cannot create a database inside a transaction, so these tests
 * truncate instead of wrapping each test in one. Deleting the tenants also
 * drops their databases.
 */
uses(DatabaseTruncation::class);

afterEach(function (): void {
    deleteAllStores();
});

it('creates a dedicated database holding only tenant tables when a store is set up', function (): void {
    $store = createStore('only-store');

    $store->run(function () use ($store): void {
        expect(DB::connection()->getDatabaseName())->toBe(config('tenancy.database.prefix').$store->getTenantKey())
            ->and(Schema::hasTable('roles'))->toBeTrue()
            ->and(Schema::hasTable('personal_access_tokens'))->toBeTrue()
            ->and(Schema::hasTable('media'))->toBeTrue()
            ->and(Schema::hasTable('activity_log'))->toBeTrue()
            ->and(Schema::hasTable('audits'))->toBeTrue()
            ->and(Schema::hasTable('tenants'))->toBeFalse()
            ->and(Schema::hasTable('countries'))->toBeFalse();
    });
});

it('does not show one store\'s staff roles to another store', function (): void {
    $firstStore = createStore('first-store');
    $secondStore = createStore('second-store');

    $firstStore->run(static fn (): Role => Role::create(['name' => 'Manager', 'guard_name' => 'staff']));

    $secondStore->run(function (): void {
        expect(Role::query()->where('name', 'Manager')->exists())->toBeFalse();
    });
});

it('does not serve one store\'s cached permissions to another store', function (): void {
    $firstStore = createStore('first-store');
    $secondStore = createStore('second-store');

    $firstStore->run(function (): void {
        Permission::create(['name' => 'edit products', 'guard_name' => 'staff']);

        expect(app(PermissionRegistrar::class)->getPermissions()->pluck('name')->all())->toContain('edit products');
    });

    $secondStore->run(function (): void {
        expect(app(PermissionRegistrar::class)->getPermissions()->pluck('name')->all())->not->toBeEmpty()->not->toContain('edit products');
    });

    expect(app(PermissionRegistrar::class)->getPermissions())->toBeEmpty();
});

it('does not show one store\'s cache entries to another store or to the platform', function (): void {
    $firstStore = createStore('first-store');
    $secondStore = createStore('second-store');

    $firstStore->run(static fn (): bool => Cache::put('greeting', 'hello from the first store'));

    $secondStore->run(function (): void {
        expect(Cache::get('greeting'))->toBeNull();
    });

    expect(Cache::get('greeting'))->toBeNull();
});
