<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Shipping\Actions\ReplaceShippingZoneRegions;
use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Exceptions\ShippingRegionTakenException;
use App\Tenant\Shipping\Models\ShippingZone;
use App\Tenant\Shipping\Services\ShippingZones;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 13: where a store ships to. A zone covers countries, or states of
 * them (Geography's codes, as tax uses), or the rest of the world. A region
 * belongs to one zone at most; a state's zone beats its country's; an
 * inactive zone's regions get no shipping. Everything is audited.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shipping-store', ['country_code' => 'NG']);

    [$this->owner, $this->viewer] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $role = Role::findOrCreate('Dispatcher', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('shipping.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        return [$owner, $viewer];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('adds, lists, renames, switches off and deletes zones, with the countries and states they cover', function (): void {
    $lagos = shippingRequest('POST', '/zones', ['name' => 'Lagos', 'regions' => [['country_code' => 'NG', 'state_code' => 'la']]])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.covers_rest_of_world', false)
        ->assertJsonPath('data.regions', [['country_code' => 'NG', 'state_code' => 'LA']])
        ->json('data.id');
    shippingZone('West Africa', ['US', 'NG']);

    shippingRequest('GET', '/zones')->assertOk()->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.name', 'Lagos')
        // Sorted by country, the whole country first.
        ->assertJsonPath('data.1.regions', [['country_code' => 'NG', 'state_code' => null], ['country_code' => 'US', 'state_code' => null]]);

    shippingRequest('PATCH', "/zones/{$lagos}", ['name' => 'Lagos mainland', 'is_active' => false])->assertOk()->assertJsonPath('data.name', 'Lagos mainland')->assertJsonPath('data.is_active', false);
    shippingRequest('POST', '/zones', ['name' => 'lagos MAINLAND'])->assertUnprocessable()->assertJsonPath('code', 'shipping_zone_name_taken');
    shippingRequest('PATCH', "/zones/{$lagos}", ['regions' => []])->assertUnprocessable()->assertJsonValidationErrors('regions');

    shippingRequest('DELETE', "/zones/{$lagos}")->assertNoContent();
    shippingRequest('GET', "/zones/{$lagos}")->assertNotFound();
    expect($this->store->run(static fn (): int => DB::table('shipping_zone_regions')->count()))->toBe(2);
});

it('takes regions only from Geography, each once, with states only for countries that list them', function (array $regions, string $error): void {
    shippingRequest('POST', '/zones', ['name' => 'Somewhere', 'regions' => $regions])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'an unknown country' => [[['country_code' => 'ZZ']], 'regions.0.country_code'],
    'a lower-case country' => [[['country_code' => 'ng']], 'regions.0.country_code'],
    'an unknown state' => [[['country_code' => 'NG', 'state_code' => 'XX']], 'regions.0.state_code'],
    'a state of a country with no states list' => [[['country_code' => 'DE', 'state_code' => 'BE']], 'regions.0.state_code'],
    'a state of the wrong country' => [[['country_code' => 'US', 'state_code' => 'LA'], ['country_code' => 'NG', 'state_code' => 'CA']], 'regions.1.state_code'],
    'the same region twice' => [[['country_code' => 'NG', 'state_code' => 'LA'], ['country_code' => 'NG', 'state_code' => 'la']], 'regions.1'],
]);

it('keeps each region in one zone at most, naming the zone that has it, while a state can sit apart from its country', function (): void {
    shippingZone('Nigeria', ['NG']);
    $lagos = shippingZone('Lagos', ['NG-LA']);

    shippingRequest('POST', '/zones', ['name' => 'Also Nigeria', 'regions' => [['country_code' => 'US'], ['country_code' => 'NG']]])
        ->assertConflict()
        ->assertJsonPath('code', 'shipping_region_taken')
        ->assertJsonPath('message', 'NG is already in the zone "Nigeria". A region can be in only one zone: take it out of that zone first.');
    shippingRequest('PUT', "/zones/{$lagos}/regions", ['regions' => [['country_code' => 'NG', 'state_code' => 'LA'], ['country_code' => 'NG']]])->assertConflict()->assertJsonPath('code', 'shipping_region_taken');

    // Nothing was half-done.
    expect($this->store->run(static fn (): array => [ShippingZone::query()->count(), DB::table('shipping_zone_regions')->count()]))->toBe([2, 2]);

    // A zone can keep its own regions when they are set again.
    shippingRequest('PUT', "/zones/{$lagos}/regions", ['regions' => [['country_code' => 'NG', 'state_code' => 'LA'], ['country_code' => 'NG', 'state_code' => 'AB']]])->assertOk()->assertJsonCount(2, 'data.regions');
    shippingRequest('PUT', "/zones/{$lagos}/regions", ['regions' => []])->assertOk()->assertJsonCount(0, 'data.regions');
});

it('keeps each region in one zone in the database too, even when two zones take it at the same moment', function (): void {
    $this->store->run(static function (): void {
        [$first, $second] = [ShippingZone::query()->create(['name' => 'First']), ShippingZone::query()->create(['name' => 'Second'])];
        $first->regions()->create(['country_code' => 'NG', 'state_code' => null]);

        expect(static fn () => $second->regions()->create(['country_code' => 'NG', 'state_code' => null]))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('shipping_zone_regions')->insert(['shipping_zone_id' => $second->id, 'country_code' => 'ng']))->toThrow(QueryException::class);

        // The other zone's change commits while this one waits on the unique key: it gets the same 409, unnamed.
        $other = SecondConnection::open();
        $other->run(static fn ($connection) => $connection->table('shipping_zone_regions')->insert(['shipping_zone_id' => $first->id, 'country_code' => 'US']));
        $committed = false;
        DB::listen(static function (Illuminate\Database\Events\QueryExecuted $query) use ($other, &$committed): void {
            if (! $committed && str_contains($query->sql, '"shipping_zone_id" != ?')) {
                $committed = true;
                $other->commit();
            }
        });

        expect(static fn () => app(ReplaceShippingZoneRegions::class)->handle($second, [new ShippingRegion('US')]))->toThrow(ShippingRegionTakenException::class, 'shipping_region_taken');
    });
});

it('lets one zone cover the rest of the world, listing no regions of its own', function (): void {
    $world = shippingZone('Rest of the world', restOfWorld: true);
    $nigeria = shippingZone('Nigeria', ['NG']);

    shippingRequest('POST', '/zones', ['name' => 'Elsewhere', 'covers_rest_of_world' => true])->assertConflict()->assertJsonPath('code', 'rest_of_world_zone_exists');
    shippingRequest('PATCH', "/zones/{$nigeria}", ['covers_rest_of_world' => true])->assertUnprocessable()->assertJsonPath('code', 'rest_of_world_zone_has_regions');
    shippingRequest('POST', '/zones', ['name' => 'Elsewhere', 'covers_rest_of_world' => true, 'regions' => [['country_code' => 'US']]])->assertUnprocessable()->assertJsonPath('code', 'rest_of_world_zone_has_regions');
    shippingRequest('PUT', "/zones/{$world}/regions", ['regions' => [['country_code' => 'US']]])->assertUnprocessable()->assertJsonPath('code', 'rest_of_world_zone_has_regions');

    // It can hand the job over.
    shippingRequest('PATCH', "/zones/{$world}", ['covers_rest_of_world' => false])->assertOk();
    shippingRequest('PUT', "/zones/{$nigeria}/regions", ['regions' => []])->assertOk();
    shippingRequest('PATCH', "/zones/{$nigeria}", ['covers_rest_of_world' => true])->assertOk()->assertJsonPath('data.covers_rest_of_world', true);
});

it('finds the zone listing a destination\'s state, else its country, else the rest of the world, and nothing when none covers it', function (): void {
    shippingZone('Nigeria', ['NG']);
    shippingZone('Lagos', ['NG-LA']);
    $usWest = shippingZone('California', ['US-CA']);

    $zoneFor = fn (string $country, ?string $state = null): ?string => $this->store->run(static fn (): ?string => app(ShippingZones::class)->for(new ShippingRegion($country, $state))?->name);

    expect([$zoneFor('NG', 'LA'), $zoneFor('NG', 'AB'), $zoneFor('NG'), $zoneFor('US', 'CA'), $zoneFor('US', 'NY'), $zoneFor('US'), $zoneFor('JP')])
        ->toBe(['Lagos', 'Nigeria', 'Nigeria', 'California', null, null, null]);

    shippingZone('Everywhere else', restOfWorld: true);
    expect([$zoneFor('US', 'NY'), $zoneFor('JP'), $zoneFor('NG', 'LA')])->toBe(['Everywhere else', 'Everywhere else', 'Lagos']);

    // A switched-off zone's regions get nothing: they never fall through to a broader zone.
    shippingRequest('PATCH', "/zones/{$usWest}", ['is_active' => false])->assertOk();
    shippingRequest('PATCH', '/zones/'.shippingRequest('GET', '/zones')->json('data.2.id'), ['is_active' => false])->assertOk()->assertJsonPath('data.name', 'Lagos');
    expect([$zoneFor('US', 'CA'), $zoneFor('NG', 'LA'), $zoneFor('NG', 'AB')])->toBe([null, null, 'Nigeria']);
});

it('audits zones, and each change to their regions as one change with the lists before and after', function (): void {
    $zone = shippingZone('West Africa', ['NG']);
    shippingRequest('PUT', "/zones/{$zone}/regions", ['regions' => [['country_code' => 'NG'], ['country_code' => 'US', 'state_code' => 'CA']]])->assertOk();
    shippingRequest('PUT', "/zones/{$zone}/regions", ['regions' => [['country_code' => 'US', 'state_code' => 'ca'], ['country_code' => 'NG']]])->assertOk();
    shippingRequest('PATCH', "/zones/{$zone}", ['name' => 'Two places'])->assertOk();
    shippingRequest('DELETE', "/zones/{$zone}")->assertNoContent();

    $audits = $this->store->run(static fn (): array => Audit::query()->where('auditable_type', 'shipping_zone')->orderBy('id')->get(['event', 'old_values', 'new_values', 'user_id'])->toArray());

    expect(array_column($audits, 'event'))->toBe(['created', 'regions_replaced', 'regions_replaced', 'updated', 'deleted'])
        ->and([$audits[1]['old_values'], $audits[1]['new_values']])->toBe([['regions' => []], ['regions' => ['NG']]])
        ->and([$audits[2]['old_values'], $audits[2]['new_values']])->toBe([['regions' => ['NG']], ['regions' => ['NG', 'US-CA']]])
        ->and(array_unique(array_column($audits, 'user_id')))->toBe([$this->owner->id]);
});

it('lets staff with shipping.view look but not change, and nobody else even look', function (): void {
    $zone = shippingZone('Nigeria', ['NG']);
    $nobody = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());

    shippingRequest('GET', '/zones', as: $this->viewer)->assertOk();
    shippingRequest('GET', "/zones/{$zone}", as: $this->viewer)->assertOk();

    foreach ([['POST', '/zones', ['name' => 'More']], ['PATCH', "/zones/{$zone}", ['name' => 'More']], ['PUT', "/zones/{$zone}/regions", ['regions' => []]], ['DELETE', "/zones/{$zone}", []]] as [$method, $path, $data]) {
        shippingRequest($method, $path, $data, as: $this->viewer)->assertForbidden();
    }

    shippingRequest('GET', '/zones', as: $nobody)->assertForbidden();
    shippingRequest('GET', "/zones/{$zone}", as: $nobody)->assertForbidden();
});
