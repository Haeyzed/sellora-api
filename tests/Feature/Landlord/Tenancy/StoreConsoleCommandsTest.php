<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\DatabaseServer;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;

/*
 * Section 9.1: running store migrations across every store skips only stores
 * with no database yet; suspended and closed stores stay migrated. Database servers are
 * added to the pool from the command line, only once they answer.
 */
uses(DatabaseTruncation::class);

afterEach(function (): void {
    deleteAllStores();
});

it('migrates every store with a database, suspended and closed ones included, and skips stores being set up, failed or purged', function (): void {
    $active = createStore('active-store');
    $suspended = createStore('suspended-store');
    $suspended->update(['status' => TenantStatus::Suspended]);
    $closed = createStore('closed-store');
    $closed->update(['status' => TenantStatus::Closed, 'status_before_closing' => TenantStatus::Active, 'closed_at' => now(), 'purge_after' => now()->addDays(90)]);
    $provisioning = Tenant::factory()->provisioning()->create();
    $failed = Tenant::factory()->create(['status' => TenantStatus::ProvisioningFailed]);
    $purging = Tenant::factory()->create(['status' => TenantStatus::Purging]);
    $purged = Tenant::factory()->create(['status' => TenantStatus::Purged]);

    expect(Artisan::call('tenants:migrate'))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain($active->id)
        ->toContain($suspended->id)
        ->toContain($closed->id)
        ->not->toContain($provisioning->id)
        ->not->toContain($failed->id)
        ->not->toContain($purging->id)
        ->not->toContain($purged->id);
});

it('runs on exactly the stores named, which is how a new store is migrated while being set up', function (): void {
    $store = createStore('named-store');
    $store->update(['status' => TenantStatus::Provisioning]);

    expect(Artisan::call('tenants:migrate', ['--tenants' => [$store->id]]))->toBe(0)
        ->and(Artisan::output())->toContain($store->id);
});

it('does nothing, instead of running on every store, when no store has a database', function (): void {
    Tenant::factory()->provisioning()->create();

    expect(Artisan::call('tenants:migrate'))->toBe(0)
        ->and(Artisan::output())->toContain('No store has a database yet.');
});

it('adds a database server only once it answers, keeping its password encrypted', function (): void {
    $centralConnection = config()->array('database.connections.central');
    $details = [
        '--name' => 'africa-2',
        '--region' => 'africa',
        '--host' => $centralConnection['host'],
        '--port' => (string) $centralConnection['port'],
        '--username' => $centralConnection['username'],
        '--capacity' => '50',
    ];

    $this->artisan('platform:add-database-server', $details)
        ->expectsQuestion('Password', 'definitely-not-the-password')
        ->assertFailed();
    expect(DatabaseServer::query()->count())->toBe(0);

    $this->artisan('platform:add-database-server', $details)
        ->expectsQuestion('Password', (string) $centralConnection['password'])
        ->assertSuccessful();

    $databaseServer = DatabaseServer::query()->sole();
    expect($databaseServer->region)->toBe('africa')
        ->and($databaseServer->capacity)->toBe(50)
        ->and($databaseServer->accepting_new_tenants)->toBeTrue()
        ->and($databaseServer->getRawOriginal('password'))->not->toBe($centralConnection['password']);

    $this->artisan('platform:add-database-server', [...$details, '--region' => 'mars'])->assertFailed();
});
