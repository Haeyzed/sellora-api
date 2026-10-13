<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Plans\Models\Plan;
use App\Landlord\Subscriptions\Enums\SubscriptionStatus;
use App\Landlord\Subscriptions\Models\Subscription;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\DatabaseServer;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\StoreRegistrationCodeNotification;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Features;
use App\Shared\Features\FeatureState;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| For DatabaseServerPlacementTest.php
|--------------------------------------------------------------------------
*/

function storeAwaitingPlacement(string $region = 'africa'): Tenant
{
    return Tenant::factory()->provisioning()->create(['hosting_region' => $region]);
}

/*
|--------------------------------------------------------------------------
| For MovePlatformDomainTest.php
|--------------------------------------------------------------------------
*/

function storeWithDomain(string $domain): Tenant
{
    $store = Tenant::factory()->create();
    $store->domains()->create(['domain' => $domain]);

    return $store;
}

/*
|--------------------------------------------------------------------------
| For PermissionSyncTest.php
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| For StoreClosureTest.php
|--------------------------------------------------------------------------
*/

function closingAdminWith(string ...$permissionNames): PlatformAdmin
{
    $role = Role::findOrCreate('Closing role with '.implode(', ', $permissionNames), PlatformAdmin::GUARD);
    $role->syncPermissions($permissionNames);

    $platformAdmin = PlatformAdmin::factory()->create();
    $platformAdmin->assignRole($role);
    enableTwoFactor($platformAdmin);

    return $platformAdmin;
}

/**
 * @param  array<string, mixed>  $data
 */
function storeClosure(string $method, Tenant $store, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($as ?? test()->storeManager, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->json($method, centralUrl("/api/v1/platform/stores/{$store->public_id}/closure"), $data);
    forgetSignIns();

    return $response;
}

/**
 * Signs a store account in and returns its token, as each kind of account would.
 */
function closingStoreToken(Tenant $store, StaffMember|Customer|Driver $account, string $guard): string
{
    return $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($account, $guard, 'test')->plainTextToken);
}

/**
 * The owner closing their own store from the store's dashboard.
 *
 * @param  array<string, mixed>  $data
 */
function closeOwnStore(array $data, ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = closingStoreToken(test()->store, $as ?? test()->storeOwner, StaffMember::GUARD);
    $response = test()->withToken($token)->postJson(storeUrl('closing-store', '/api/v1/staff/store/closure'), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @return array<string, int>
 */
function closingStoreTokenCounts(Tenant $store): array
{
    return $store->run(static fn (): array => PersonalAccessToken::query()
        ->selectRaw('tokenable_type, count(*) as total')
        ->groupBy('tokenable_type')
        ->orderBy('tokenable_type')
        ->pluck('total', 'tokenable_type')
        ->map(static fn (mixed $total): int => (int) $total)
        ->all());
}

/*
|--------------------------------------------------------------------------
| For StoreExportTest.php
|--------------------------------------------------------------------------
*/

function exportAdminWith(string ...$permissionNames): PlatformAdmin
{
    $role = Role::findOrCreate('Export role with '.implode(', ', $permissionNames), PlatformAdmin::GUARD);
    $role->syncPermissions($permissionNames);

    $platformAdmin = PlatformAdmin::factory()->create();
    $platformAdmin->assignRole($role);
    enableTwoFactor($platformAdmin);

    return $platformAdmin;
}

function platformExports(string $method, Tenant $store, string $path = '', ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($as ?? test()->exporter, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->json($method, centralUrl("/api/v1/platform/stores/{$store->public_id}/exports{$path}"));
    forgetSignIns();

    return $response;
}

function ownerExports(string $method, string $path = '', ?StaffMember $as = null, string $subdomain = 'exported-store'): TestResponse
{
    forgetSignIns();
    $store = Tenant::query()->whereHas('domains', static fn ($query) => $query->where('domain', Tenant::platformDomainFor($subdomain)))->firstOrFail();
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->exportOwner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, "/api/v1/staff/store/exports{$path}"));
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Every file in a downloaded export, by path.
 *
 * @return array<string, string>
 */
function exportedFiles(TestResponse $download): array
{
    $zipPath = tempnam(sys_get_temp_dir(), 'export-test');
    file_put_contents($zipPath, $download->streamedContent());

    $zip = new ZipArchive;
    $zip->open($zipPath);
    $files = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $files[$name] = (string) $zip->getFromName($name);
    }

    $zip->close();
    unlink($zipPath);

    return $files;
}

/**
 * Plants every kind of secret a store holds, and returns them, so a test can prove none is exported.
 *
 * @return list<string>
 */
function plantStoreSecrets(Tenant $store): array
{
    return $store->run(static function (): array {
        $staffMember = StaffMember::query()->firstOrFail();
        $twoFactorSecret = enableTwoFactor($staffMember, 'recovery-code-one');
        $customer = Customer::factory()->create(['name' => 'Chidi Customer', 'email' => 'chidi@shopper.example']);
        $driver = Driver::factory()->create(['name' => 'Dayo Driver']);
        $invitation = StaffInvitation::factory()->create();
        $accessToken = app(AccessTokenIssuer::class)->issue($customer, Customer::GUARD, 'phone');

        DB::table('staff_member_password_reset_tokens')->insert(['email' => $staffMember->email, 'token' => 'reset-token-secret-value', 'created_at' => now()]);
        DB::table('audits')->insert([
            'event' => 'updated', 'auditable_type' => 'customer', 'auditable_id' => $customer->id,
            'old_values' => json_encode(['password' => 'audited-old-password-hash', 'name' => 'Old Name']),
            'new_values' => json_encode(['password' => 'audited-new-password-hash', 'name' => 'Chidi Customer']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        activity('test')->withProperties(['api_key' => 'activity-api-key-secret', 'note' => 'kept in the export'])->log('Connected a gateway');

        return [
            $staffMember->getRawOriginal('password'),
            $twoFactorSecret,
            (string) $staffMember->getRawOriginal('two_factor_secret'),
            app(App\Shared\Auth\TwoFactor\TwoFactorAuthenticator::class)->hashRecoveryCode('recovery-code-one'),
            $customer->getRawOriginal('password'),
            $driver->getRawOriginal('pin'),
            $invitation->token_hash,
            hash('sha256', explode('|', $accessToken->plainTextToken, 2)[1]),
            'reset-token-secret-value',
            'audited-old-password-hash',
            'audited-new-password-hash',
            'activity-api-key-secret',
        ];
    });
}

/*
|--------------------------------------------------------------------------
| For StoreManagementTest.php
|--------------------------------------------------------------------------
*/

function platformAdminWith(string ...$permissionNames): PlatformAdmin
{
    $role = Role::findOrCreate('Role with '.implode(', ', $permissionNames), PlatformAdmin::GUARD);
    $role->syncPermissions($permissionNames);

    $platformAdmin = PlatformAdmin::factory()->create();
    $platformAdmin->assignRole($role);
    enableTwoFactor($platformAdmin);

    return $platformAdmin;
}

function subscribedStore(?string $subdomain = null): Tenant
{
    $store = $subdomain === null ? createStoreRecord() : createStore($subdomain);
    Subscription::factory()->create(['tenant_id' => $store->id, 'plan_id' => test()->plan->id, 'status' => SubscriptionStatus::Active]);

    return $store;
}

/**
 * @param  array<string, mixed>  $data
 */
function manageStores(string $method, string $path, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($as ?? test()->operator, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->json($method, centralUrl('/api/v1/platform/stores'.$path), $data);
    forgetSignIns();

    return $response;
}

function storeFeatureState(Tenant $store, string $featureKey): FeatureState
{
    return app(Features::class)->stateFor($store->id, $featureKey);
}

/*
|--------------------------------------------------------------------------
| For StorePurgeTest.php
|--------------------------------------------------------------------------
*/

function closeStoreForPurging(Tenant $store, DateTimeInterface $purgeAfter): void
{
    $store->update([
        'status' => TenantStatus::Closed,
        'status_before_closing' => TenantStatus::Active,
        'closed_at' => now()->subDays(90),
        'closure_reason' => 'The owner wrote that they are moving abroad',
        'purge_after' => $purgeAfter,
    ]);
}

function purgedStoreDatabaseExists(Tenant $store): bool
{
    $databaseConfig = $store->database();

    return $databaseConfig->manager()->databaseExists((string) $databaseConfig->getName());
}

function restoreForPurgeTest(Tenant $store): TestResponse
{
    $role = Role::findOrCreate('Purge test store manager', PlatformAdmin::GUARD);
    $role->syncPermissions(['stores.view', 'stores.manage']);
    $platformAdmin = PlatformAdmin::factory()->create();
    $platformAdmin->assignRole($role);
    enableTwoFactor($platformAdmin);

    forgetSignIns();
    $response = test()
        ->withToken(app(AccessTokenIssuer::class)->issue($platformAdmin, PlatformAdmin::GUARD, 'test')->plainTextToken)
        ->deleteJson(centralUrl("/api/v1/platform/stores/{$store->public_id}/closure"));
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For StoreRegistrationTest.php
|--------------------------------------------------------------------------
*/

/**
 * Everything registration needs: a free plan, a version in force of every legal document, a database server, and the test countries (seedWorld()).
 */
function openStoreRegistration(): DatabaseServer
{
    Plan::factory()->create(['code' => 'free', 'name' => 'Free']);

    foreach (LegalDocumentType::cases() as $type) {
        LegalDocument::factory()->ofType($type)->inForce()->create();
    }

    seedWorld();

    return DatabaseServer::factory()->inRegion('africa')->create(['name' => 'africa-1', 'capacity' => 10]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storeSignUp(array $overrides = []): array
{
    return [
        'store_name' => 'Ada Fabrics',
        'subdomain' => 'ada-fabrics',
        'owner_name' => 'Ada Obi',
        'email' => 'ada@example.com',
        'password' => OWNER_PASSWORD,
        'password_confirmation' => OWNER_PASSWORD,
        'country_code' => 'NG',
        'hosting_region' => 'africa',
        'accepted_legal_documents' => array_map(static fn (LegalDocument $legalDocument): string => $legalDocument->public_id, array_values(LegalDocument::inForce())),
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function signUpForStore(array $overrides = []): TestResponse
{
    return test()->postJson(centralUrl('/api/v1/store-registrations'), storeSignUp($overrides));
}

function confirmStoreSignUp(string $storeRegistrationId, string $code): TestResponse
{
    return test()->postJson(centralUrl("/api/v1/store-registrations/{$storeRegistrationId}/verification"), ['code' => $code]);
}

/**
 * The last code emailed to the address.
 */
function lastStoreRegistrationCode(string $email = 'ada@example.com'): string
{
    $codes = [];

    Notification::assertSentOnDemand(StoreRegistrationCodeNotification::class, static function (StoreRegistrationCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$codes): bool {
        if ($notifiable->routes['mail'] === $email) {
            $codes[] = $notification->code();
        }

        return true;
    });

    return $codes === [] ? throw new RuntimeException("No code was sent to {$email}.") : end($codes);
}

/*
|--------------------------------------------------------------------------
| For TenantProvisioningTest.php
|--------------------------------------------------------------------------
*/

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
