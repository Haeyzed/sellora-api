<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\StoreDatabase;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\StoreDatabaseTemplate;

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

/**
 * Everything about a store database that setup decides: tables, columns, constraints, indexes, migrations run, and the seeded roles and permissions.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function storeDatabaseSnapshot(Tenant $store): array
{
    return $store->run(static fn (): array => array_map(
        static fn (string $sql): array => array_map(static fn (object $row): array => (array) $row, DB::select($sql)),
        [
            'columns' => "select table_name, column_name, data_type, is_nullable, column_default from information_schema.columns where table_schema = 'public' order by 1, 2",
            'constraints' => "select conrelid::regclass::text as on_table, conname, pg_get_constraintdef(oid) as definition from pg_constraint where connamespace = 'public'::regnamespace order by 1, 2",
            'indexes' => "select indexname, indexdef from pg_indexes where schemaname = 'public' order by 1",
            'migrations' => 'select migration, batch from migrations order by 1',
            'roles' => 'select name, guard_name from roles order by 1',
            'permissions' => 'select name, guard_name from permissions order by 1',
            'role_permissions' => 'select r.name as role, p.name as permission from role_has_permissions rp join roles r on r.id = rp.role_id join permissions p on p.id = rp.permission_id order by 1, 2',
        ],
    ));
}

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

/**
 * Every row of every table and every ID counter in a store database.
 *
 * @return array<string, mixed>
 */
function storeDatabaseContents(Tenant $store): array
{
    return $store->run(static function (): array {
        $contents = ['counters' => array_map(static fn (object $row): array => (array) $row, DB::select('select sequencename, last_value from pg_sequences order by 1'))];

        foreach (DB::select("select tablename from pg_tables where schemaname = 'public' order by 1") as $table) {
            $contents[$table->tablename] = DB::selectOne("select coalesce(json_agg(t order by t::text), '[]') as rows from \"{$table->tablename}\" t")->rows;
        }

        return $contents;
    });
}

function storeDatabaseIdentity(Tenant $store): int
{
    return (int) DB::selectOne('select oid from pg_database where datname = ?', [$store->database()->getName()])->oid;
}

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

it('never reuses a test store\'s database whose schema the test changed', function (): void {
    $changed = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($changed);
    $changed->run(static fn () => DB::statement('create index roles_by_guard on roles (guard_name)'));
    $changedDatabase = storeDatabaseIdentity($changed);
    deleteAllStores();

    $next = Tenant::factory()->create();
    StoreDatabaseTemplate::copyInto($next);

    expect(storeDatabaseIdentity($next))->not->toBe($changedDatabase)
        ->and(DB::selectOne('select count(*) as found from pg_database where oid = ?', [$changedDatabase])->found)->toBe(0)
        ->and($next->run(static fn (): array => DB::select("select 1 from pg_indexes where indexname = 'roles_by_guard'")))->toBe([]);
});
