<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\NoDatabaseServerAvailableException;
use App\Landlord\Tenancy\Models\DatabaseServer;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\DatabaseServerPlacement;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

/*
 * Section 9.1: a store's database goes on the least full server in its own
 * region that accepts new stores, and a server never goes past its capacity.
 */
uses(LazilyRefreshDatabase::class);

function storeAwaitingPlacement(string $region = 'africa'): Tenant
{
    return Tenant::factory()->provisioning()->create(['hosting_region' => $region]);
}

it('places a store on the least full server in its region that accepts new stores', function (): void {
    DatabaseServer::factory()->inRegion('africa')->create(['name' => 'busy', 'capacity' => 10])->forceFill(['tenant_count' => 5])->save();
    $quiet = DatabaseServer::factory()->inRegion('africa')->create(['name' => 'quiet', 'capacity' => 10]);
    DatabaseServer::factory()->inRegion('africa')->create(['name' => 'closed', 'capacity' => 100, 'accepting_new_tenants' => false]);
    DatabaseServer::factory()->inRegion('eu')->create(['name' => 'elsewhere', 'capacity' => 100]);

    $store = storeAwaitingPlacement();
    app(DatabaseServerPlacement::class)->place($store);

    expect($store->refresh()->database_server_id)->toBe($quiet->id)
        ->and($quiet->refresh()->tenant_count)->toBe(1);
});

it('keeps a placed store on its server when placing it again', function (): void {
    $databaseServer = DatabaseServer::factory()->inRegion('africa')->create();
    $store = storeAwaitingPlacement();

    app(DatabaseServerPlacement::class)->place($store);
    app(DatabaseServerPlacement::class)->place($store);

    expect($databaseServer->refresh()->tenant_count)->toBe(1);
});

it('never places more stores on a server than its capacity', function (): void {
    $databaseServer = DatabaseServer::factory()->inRegion('africa')->create(['capacity' => 1]);

    app(DatabaseServerPlacement::class)->place(storeAwaitingPlacement());

    expect(fn () => app(DatabaseServerPlacement::class)->place(storeAwaitingPlacement()))->toThrow(NoDatabaseServerAvailableException::class)
        ->and($databaseServer->refresh()->tenant_count)->toBe(1)
        ->and(Tenant::query()->where('status', TenantStatus::Provisioning)->whereNull('database_server_id')->count())->toBe(1);
});

it('never places a store outside its region', function (): void {
    DatabaseServer::factory()->inRegion('eu')->create();

    expect(fn () => app(DatabaseServerPlacement::class)->place(storeAwaitingPlacement('africa')))->toThrow(NoDatabaseServerAvailableException::class);
});
