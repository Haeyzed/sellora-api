<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\StoreDatabase;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\StoreDatabaseTemplate;
use Tests\Support\TruncatesCentralDatabase;

/*
 * PostgreSQL cannot create a database inside a transaction, so these tests
 * truncate instead of wrapping each test in one. Deleting the tenants also
 * drops their databases.
 */
uses(TruncatesCentralDatabase::class);

afterEach(function (): void {
    deleteAllStores();
});

it('creates a dedicated database holding only tenant tables when a store is set up', function (): void {
    $store = createStore('only-store');

    $store->run(function () use ($store): void {
        // Each parallel test process names store databases with a prefix of its own (TestCase).
        expect(config('tenancy.database.prefix'))->toEndWith('_'.(ParallelTesting::token() ?: '0').'_')
            ->and(DB::connection()->getDatabaseName())->toBe(config('tenancy.database.prefix').$store->getTenantKey())
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

it('gives test stores a copy of the template that matches a store database set up for real', function (): void {
    // Section 15: createStore() copies one prepared database instead of migrating; the copy must be exactly what setup makes.
    $copied = createStore('copied-store');
    $prepared = Tenant::factory()->create();
    app(StoreDatabase::class)->prepare($prepared);

    $copy = storeDatabaseSnapshot($copied);
    expect($copy['columns'])->not->toBeEmpty()
        ->and($copy['migrations'])->toHaveCount(count(glob(database_path('migrations/tenant/*.php')) ?: []))
        ->and($copy['permissions'])->not->toBeEmpty()
        ->and($copy)->toBe(storeDatabaseSnapshot($prepared));
});

it('reuses a test store\'s database for the next test store only once it is identical to a fresh copy', function (): void {
    $used = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($used);
    $used->run(static function (): void {
        StaffMember::factory()->count(2)->create();
        Role::findOrCreate('Manager', 'staff');
        Permission::query()->where('name', 'catalog.view')->delete();
        DB::select("select nextval(pg_get_serial_sequence('roles', 'id'))");
    });
    $usedDatabase = storeDatabaseIdentity($used);
    deleteAllStores();

    $reused = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($reused);
    $fresh = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($fresh, mayReuse: false);

    expect(storeDatabaseIdentity($reused))->toBe($usedDatabase)
        ->and(storeDatabaseIdentity($fresh))->not->toBe($usedDatabase)
        ->and(storeDatabaseContents($reused))->toBe(storeDatabaseContents($fresh));
});

it('never reuses a test store\'s database whose schema the test changed', function (string $change): void {
    $changed = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($changed);
    $changed->run(static fn () => DB::unprepared($change));
    $changedDatabase = storeDatabaseIdentity($changed);
    deleteAllStores();

    $next = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($next);

    expect(storeDatabaseIdentity($next))->not->toBe($changedDatabase)
        ->and(DB::selectOne('select count(*) as found from pg_database where oid = ?', [$changedDatabase])->found)->toBe(0)
        ->and(storeDatabaseSnapshot($next))->toBe(storeDatabaseSnapshot(tap(Tenant::factory()->create(), static fn (Tenant $fresh) => StoreDatabaseTemplate::copyInto($fresh, mayReuse: false))));
})->with([
    'an index' => ['create index roles_by_guard on roles (guard_name)'],
    'a column' => ['alter table roles add column note text'],
    'a column default' => ["alter table roles alter column guard_name set default 'staff'"],
    'a nullable column' => ['alter table roles alter column guard_name drop not null'],
    'a constraint' => ['alter table roles add constraint roles_name_not_blank check (name <> \'\')'],
    'a table without rows' => ['create table scratch (id int)'],
    'a function' => ['create function scratch_answer() returns int language sql as $$ select 42 $$'],
    'a trigger' => ['create function roles_touch() returns trigger language plpgsql as $$ begin return new; end $$; create trigger roles_touch before update on roles for each row execute function roles_touch()'],
]);

it('still reuses a test store\'s database when a stale connection to it was left open', function (): void {
    $used = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($used);
    $usedDatabase = storeDatabaseIdentity($used);

    // A connection some earlier code forgot to close.
    config(['database.connections.stale' => [...config()->array('database.connections.central'), 'database' => $used->database()->getName()]]);
    DB::connection('stale')->select('select 1');

    deleteAllStores();
    DB::purge('stale');

    $reused = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($reused);

    expect(storeDatabaseIdentity($reused))->toBe($usedDatabase);
});
