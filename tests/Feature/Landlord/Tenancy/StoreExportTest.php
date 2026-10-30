<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\StoreExportStatus;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Jobs\BuildStoreExport;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\StoreExportRetention;
use App\Shared\Auth\Models\Role;
use App\Shared\Privacy\Exceptions\UnclassifiedStoreDataException;
use App\Shared\Tenancy\StoreExportReadyNotification;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Database\Seeders\Landlord\PlatformPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 9.1: a store export is a ZIP of JSON Lines files plus a manifest,
 * built on the bulk queue one at a time per store, kept privately for 7 days
 * and downloadable only by whoever requested it, with every download logged.
 * Contents are decided column by column and fail closed: secrets never leave
 * the store, not even inside audit and activity records.
 */
uses(TruncatesCentralDatabase::class);

const EXPORT_OWNER_PASSWORD = 'a-long-export-owner-password';

beforeEach(function (): void {
    foreach ([config()->string('tenancy.store_exports.default_disk'), ...config()->array('tenancy.store_exports.disks')] as $disk) {
        Storage::fake($disk);
    }
    $this->seed(PlatformPermissionSeeder::class);
    $this->exporter = exportAdminWith('stores.view', 'stores.export');

    $this->store = createStore('exported-store');
    $this->exportOwner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create(['name' => 'Ola Owner', 'email' => 'ola@exported.example', 'password' => EXPORT_OWNER_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('exports the store\'s data without a single secret, and says what it left out', function (): void {
    $secrets = plantStoreSecrets($this->store);

    $exportId = platformExports('POST', $this->store)->assertAccepted()->assertJsonPath('data.status', 'ready')->json('data.id');
    $files = exportedFiles(platformExports('GET', $this->store, "/{$exportId}/download")->assertOk());
    $everything = implode("\n", $files);

    foreach ($secrets as $secret) {
        expect($everything)->not->toContain($secret);
    }

    $manifest = json_decode($files['manifest.json'], true);
    expect($files)->toHaveKeys(['manifest.json', 'tables/staff_members.jsonl', 'tables/customers.jsonl', 'tables/drivers.jsonl', 'tables/audits.jsonl', 'tables/activity_log.jsonl'])
        ->not->toHaveKey('tables/personal_access_tokens.jsonl')
        ->and($files['tables/customers.jsonl'])->toContain('chidi@shopper.example')
        ->and($files['tables/staff_members.jsonl'])->toContain('ola@exported.example')
        ->and($files['tables/audits.jsonl'])->toContain('Chidi Customer')->toContain('[redacted]')
        ->and($files['tables/activity_log.jsonl'])->toContain('kept in the export')
        ->and($manifest['left_out']['personal_access_tokens'])->toBe('Sign-in tokens are secrets.')
        ->and($manifest['left_out']['staff_members'])->toHaveKey('password');
});

it('refuses to export a store with a column nobody classified, instead of exporting it', function (): void {
    Queue::fake();
    Notification::fake();
    $this->store->run(static fn () => Schema::table('customers', static fn (Blueprint $table) => $table->string('secret_note')->nullable()));

    $exportId = platformExports('POST', $this->store)->assertAccepted()->assertJsonPath('data.status', 'queued')->json('data.id');
    $storeExport = StoreExport::query()->where('public_id', $exportId)->sole();

    expect(fn () => app()->call([new BuildStoreExport($storeExport->id), 'handle']))->toThrow(UnclassifiedStoreDataException::class, 'customers.secret_note');

    (new BuildStoreExport($storeExport->id))->failed(null);
    expect($storeExport->refresh()->status)->toBe(StoreExportStatus::Failed)
        ->and(Storage::disk($storeExport->disk)->allFiles())->toBe([]);
    Notification::assertNothingSent();
});

it('keeps each export on its store\'s regional disk, and on the default disk for regions without one', function (): void {
    Queue::fake();
    config()->set('tenancy.store_exports.disks', ['africa' => 'store_exports_africa', 'eu' => 'store_exports_eu']);
    $euStore = createStore('eu-exported-store', ['hosting_region' => 'eu']);
    $unlistedStore = createStore('unlisted-exported-store', ['hosting_region' => 'us']);

    platformExports('POST', $this->store)->assertAccepted();
    platformExports('POST', $euStore)->assertAccepted();
    platformExports('POST', $unlistedStore)->assertAccepted();

    expect(StoreExport::query()->where('tenant_id', $this->store->id)->value('disk'))->toBe('store_exports_africa')
        ->and(StoreExport::query()->where('tenant_id', $euStore->id)->value('disk'))->toBe('store_exports_eu')
        ->and(StoreExport::query()->where('tenant_id', $unlistedStore->id)->value('disk'))->toBe('store_exports');
});

it('defines every regional export disk as a private disk, on this machine unless set otherwise', function (): void {
    foreach (config()->array('tenancy.store_exports.disks') as $region => $disk) {
        expect(config()->array("filesystems.disks.{$disk}"))->toMatchArray([
            'driver' => 'local',
            'root' => storage_path("app/store-exports/{$region}"),
            'visibility' => 'private',
            'serve' => false,
        ]);
    }
});

it('puts a region\'s S3 export bucket in that region\'s cloud region, or the default AWS region', function (): void {
    $environment = ['STORE_EXPORT_EU_DRIVER' => 's3', 'STORE_EXPORT_EU_BUCKET' => 'exports-eu', 'REGION_EU_CLOUD_REGION' => 'eu-central-1', 'STORE_EXPORT_US_DRIVER' => 's3', 'REGION_US_CLOUD_REGION' => '', 'AWS_DEFAULT_REGION' => 'us-east-2'];
    $previous = [];

    foreach ($environment as $name => $value) {
        $previous[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
        $_SERVER[$name] = $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }

    try {
        $disks = (require base_path('config/filesystems.php'))['disks'];
    } finally {
        foreach ($previous as $name => [$server, $env, $process]) {
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }

            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }

            putenv($process === false ? $name : "{$name}={$process}");
        }
    }

    expect($disks['store_exports_eu'])->toMatchArray(['driver' => 's3', 'bucket' => 'exports-eu', 'region' => 'eu-central-1', 'visibility' => 'private'])
        ->and($disks['store_exports_us'])->toMatchArray(['driver' => 's3', 'region' => 'us-east-2']);
});

it('builds exports on the bulk queue, one at a time per store', function (): void {
    Queue::fake();

    platformExports('POST', $this->store)->assertAccepted();
    platformExports('POST', $this->store)->assertConflict()->assertJsonPath('code', 'store_export_in_progress');

    Queue::assertPushedOn('bulk', BuildStoreExport::class);
    Queue::assertPushed(BuildStoreExport::class, 1);
});

it('refuses a second export in progress in the database too', function (): void {
    $insertQueued = fn (): bool => DB::table('store_exports')->insert([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $this->store->id, 'requested_by_type' => 'platform_admin', 'requested_by_id' => 'someone',
        'status' => 'queued', 'disk' => 'store_exports', 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $insertQueued();

    expect(fn () => DB::transaction($insertQueued))->toThrow(QueryException::class, 'store_exports_one_in_progress');
});

it('needs the stores.export permission, even for admins who manage stores', function (): void {
    $manager = exportAdminWith('stores.view', 'stores.manage', 'stores.grant');

    platformExports('POST', $this->store, as: $manager)->assertForbidden();
    expect(StoreExport::query()->count())->toBe(0);
});

it('lets only the admin who requested an export see or download it, and logs every download', function (): void {
    $exportId = platformExports('POST', $this->store)->assertAccepted()->json('data.id');
    $colleague = exportAdminWith('stores.view', 'stores.export');

    platformExports('GET', $this->store, "/{$exportId}", $colleague)->assertNotFound()->assertJsonPath('code', 'store_export_not_found');
    platformExports('GET', $this->store, "/{$exportId}/download", $colleague)->assertNotFound();
    platformExports('GET', $this->store, '', $colleague)->assertOk()->assertJsonCount(0, 'data');

    platformExports('GET', $this->store)->assertOk()->assertJsonCount(1, 'data');
    platformExports('GET', $this->store, "/{$exportId}/download")->assertOk();
    platformExports('GET', $this->store, "/{$exportId}/download")->assertOk();

    expect(Activity::query()->where('event', 'store_export_downloaded')->where('causer_id', (string) $this->exporter->id)->count())->toBe(2);
});

it('can no longer be downloaded after 7 days, when its file is deleted', function (): void {
    $exportId = platformExports('POST', $this->store)->assertAccepted()->json('data.id');
    $storeExport = StoreExport::query()->where('public_id', $exportId)->sole();
    Storage::disk($storeExport->disk)->assertExists((string) $storeExport->path);

    $this->travel(7)->days();
    $this->travel(1)->minutes();

    platformExports('GET', $this->store, "/{$exportId}")->assertOk()->assertJsonPath('data.status', 'expired');
    platformExports('GET', $this->store, "/{$exportId}/download")->assertConflict()->assertJsonPath('code', 'store_export_not_ready');

    expect(app(StoreExportRetention::class)->purgeOlderThan(CarbonImmutable::now()->subDays(config()->integer('retention.periods.store_exports'))))->toBe(1);
    Storage::disk($storeExport->disk)->assertMissing((string) $storeExport->path);
    expect(StoreExport::query()->count())->toBe(0);
});

it('exports a closed store for a platform admin', function (): void {
    $this->store->update(['status' => TenantStatus::Closed, 'status_before_closing' => TenantStatus::Active, 'closed_at' => now(), 'purge_after' => now()->addDays(90)]);

    platformExports('POST', $this->store)->assertAccepted()->assertJsonPath('data.status', 'ready');
});

it('lets the owner export their own store, recording them as the requester', function (): void {
    $exportId = ownerExports('POST')->assertAccepted()->json('data.id');

    ownerExports('GET', "/{$exportId}")->assertOk()->assertJsonPath('data.status', 'ready');
    $files = exportedFiles(ownerExports('GET', "/{$exportId}/download")->assertOk());

    $storeExport = StoreExport::query()->where('public_id', $exportId)->sole();
    expect($files)->toHaveKey('tables/staff_members.jsonl')
        ->and($storeExport->requested_by_type)->toBe('staff_member')
        ->and($storeExport->requested_by_id)->toBe($this->exportOwner->public_id)
        ->and(Activity::query()->where('event', 'store_export_requested')->whereNull('causer_type')->whereNull('causer_id')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'store_export_downloaded')->whereNull('causer_type')->whereNull('causer_id')->count())->toBe(1);

    platformExports('GET', $this->store, "/{$exportId}/download")->assertNotFound();
});

it('lets only the owner export the store, whatever their roles', function (): void {
    $manager = $this->store->run(static function (): StaffMember {
        $everything = Role::findOrCreate('Everything', StaffMember::GUARD);
        $everything->syncPermissions(Spatie\Permission\Models\Permission::query()->where('guard_name', StaffMember::GUARD)->get());
        $manager = StaffMember::factory()->create();
        $manager->assignRole($everything);

        return $manager;
    });

    ownerExports('POST', as: $manager)->assertForbidden();
    expect(StoreExport::query()->count())->toBe(0);
});

it('never puts another store\'s data in an export, nor lets another store reach it', function (): void {
    $otherStore = createStore('other-exported-store');
    $otherOwner = $otherStore->run(static function (): StaffMember {
        Customer::factory()->create(['email' => 'someone@other-store.example']);
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    $exportId = ownerExports('POST')->assertAccepted()->json('data.id');
    $files = exportedFiles(ownerExports('GET', "/{$exportId}/download")->assertOk());

    expect(implode("\n", $files))->not->toContain('someone@other-store.example');
    ownerExports('GET', "/{$exportId}", $otherOwner, 'other-exported-store')->assertNotFound();
    ownerExports('GET', "/{$exportId}/download", $otherOwner, 'other-exported-store')->assertNotFound();
});

it('emails whoever asked for an export once it is ready, linking to their dashboard rather than the file', function (): void {
    Notification::fake();

    $adminExportId = platformExports('POST', $this->store)->assertAccepted()->json('data.id');
    $ownerExportId = ownerExports('POST')->assertAccepted()->json('data.id');

    $adminUrl = strtr(config()->string('tenancy.store_exports.ready_urls.platform_admin'), ['{store}' => $this->store->public_id, '{export}' => $adminExportId]);
    $ownerUrl = strtr(config()->string('tenancy.store_exports.ready_urls.staff_member'), ['{domain}' => Tenant::platformDomainFor('exported-store'), '{export}' => $ownerExportId]);

    Notification::assertSentTo($this->exporter, StoreExportReadyNotification::class, static fn (StoreExportReadyNotification $notification): bool => $notification->exportUrl() === $adminUrl);
    Notification::assertSentTo($this->exportOwner, StoreExportReadyNotification::class, static fn (StoreExportReadyNotification $notification): bool => $notification->exportUrl() === $ownerUrl);
    Notification::assertSentTimes(StoreExportReadyNotification::class, 2);
    expect($ownerUrl)->not->toContain('{');
});
