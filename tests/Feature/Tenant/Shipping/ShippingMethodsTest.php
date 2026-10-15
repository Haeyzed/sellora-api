<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Shipping\Actions\SaveShippingMethod;
use App\Tenant\Shipping\Data\ShippingMethodDetails;
use App\Tenant\Shipping\Exceptions\ShippingRatesOverlapException;
use App\Tenant\Shipping\Models\ShippingMethod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 13: what shipping costs. A method ships or is delivered within a
 * zone, or is picked up at a location; it is priced by one flat bracket, or
 * by brackets of weight or order value that never overlap (gaps are allowed,
 * mean "not offered", and are warned about). Amounts are in the base
 * currency, with optional exact amounts per currency. Methods are archived,
 * never deleted, and a zone with live methods can't be deleted.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shipping-store', ['country_code' => 'NG']);

    [$this->owner, $this->viewer, $this->location] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $role = Role::findOrCreate('Dispatcher', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('shipping.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);
        offerStoreCurrency('USD', '0.001');

        return [$owner, $viewer, StockLocation::query()->sole()->public_id];
    });

    $this->zone = shippingZone('Nigeria', ['NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('adds a flat method, priced in the base currency with exact amounts in others, free over a threshold', function (): void {
    $method = shippingRequest('POST', '/methods', [
        'zone' => $this->zone,
        'name' => ['en' => 'Standard delivery'],
        'fulfilment' => 'ship',
        'pricing' => 'flat',
        'rates' => [['from' => 0, 'to' => null, 'amount' => 250000, 'currency_amounts' => [['currency' => 'USD', 'amount' => 299]]]],
        'free_over' => 5000000,
        'currency_amounts' => [['currency' => 'USD', 'free_over' => 5000]],
    ])->assertCreated()->json('data');

    expect($method)->toMatchArray([
        'zone' => ['id' => $this->zone, 'name' => 'Nigeria'],
        'name' => ['en' => 'Standard delivery'],
        'fulfilment' => 'ship',
        'pricing' => 'flat',
        'currency_amounts' => [['currency' => 'USD', 'free_over' => ['amount' => 5000, 'currency' => 'USD', 'formatted' => '$50.00']]],
        'warnings' => [],
        'pickup' => null,
        'is_active' => true,
        'archived_at' => null,
    ])
        ->and($method['free_over']['amount'])->toBe(5000000)
        ->and($method['rates'][0])->toMatchArray(['from' => 0, 'to' => null])
        ->and($method['rates'][0]['amount']['amount'])->toBe(250000)
        ->and($method['rates'][0]['currency_amounts'][0]['amount']['amount'])->toBe(299);

    shippingRequest('GET', '/methods')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $method['id']);
    shippingRequest('GET', "/methods/{$method['id']}", as: $this->viewer)->assertOk();
});

it('prices by weight or order value in brackets that never overlap, warning where they leave gaps', function (string $pricing): void {
    $method = shippingRequest('POST', '/methods', [
        'zone' => $this->zone,
        'name' => ['en' => 'By the bracket'],
        'fulfilment' => 'own_delivery',
        'pricing' => $pricing,
        'rates' => [['from' => 5000, 'to' => 10000, 'amount' => 3000], ['from' => 0, 'to' => 2000, 'amount' => 1000], ['from' => 2000, 'to' => 5000, 'amount' => 0]],
    ])->assertCreated();

    // Sorted by where they start; touching brackets are no overlap. Above the last one it isn't offered.
    expect($method->json('data.rates.*.from'))->toBe([0, 2000, 5000])
        ->and($method->json('data.warnings'))->toBe([['code' => 'not_offered', 'from' => 10000, 'to' => null]]);

    $id = $method->json('data.id');
    $gappy = shippingRequest('PATCH', "/methods/{$id}", ['rates' => [['from' => 500, 'to' => 1000, 'amount' => 1], ['from' => 3000, 'to' => null, 'amount' => 2]]])->assertOk();
    expect($gappy->json('data.warnings'))->toBe([['code' => 'not_offered', 'from' => 0, 'to' => 500], ['code' => 'not_offered', 'from' => 1000, 'to' => 3000]]);
})->with(['weight', 'order_value']);

it('refuses brackets that overlap, run backwards, or don\'t make one flat price', function (string $pricing, array $rates, string $error): void {
    shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Bad'], 'fulfilment' => 'ship', 'pricing' => $pricing, 'rates' => $rates])
        ->assertUnprocessable()
        // Caught by validation, before the database has to refuse it.
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors($error);

    expect($this->store->run(static fn (): int => ShippingMethod::query()->count()))->toBe(0);
})->with([
    'overlapping' => ['weight', [['from' => 0, 'to' => 1000, 'amount' => 1], ['from' => 999, 'to' => 2000, 'amount' => 2]], 'rates'],
    'one inside another' => ['order_value', [['from' => 0, 'to' => null, 'amount' => 1], ['from' => 5000, 'to' => 6000, 'amount' => 2]], 'rates'],
    'the same bracket twice' => ['weight', [['from' => 0, 'to' => 10, 'amount' => 1], ['from' => 0, 'to' => 10, 'amount' => 1]], 'rates'],
    'ending where it starts' => ['weight', [['from' => 10, 'to' => 10, 'amount' => 1]], 'rates.0.to'],
    'running backwards' => ['weight', [['from' => 10, 'to' => 5, 'amount' => 1]], 'rates.0.to'],
    'a negative price' => ['weight', [['from' => 0, 'to' => null, 'amount' => -1]], 'rates.0.amount'],
    'a decimal price' => ['weight', [['from' => 0, 'to' => null, 'amount' => 12.5]], 'rates.0.amount'],
    'a price in a currency not offered' => ['flat', [['from' => 0, 'to' => null, 'amount' => 1, 'currency_amounts' => [['currency' => 'EUR', 'amount' => 1]]]], 'rates.0.currency_amounts.0.currency'],
    'the base currency as an exact price' => ['flat', [['from' => 0, 'to' => null, 'amount' => 1, 'currency_amounts' => [['currency' => 'NGN', 'amount' => 1]]]], 'rates.0.currency_amounts.0.currency'],
    'one currency twice' => ['flat', [['from' => 0, 'to' => null, 'amount' => 1, 'currency_amounts' => [['currency' => 'USD', 'amount' => 1], ['currency' => 'USD', 'amount' => 2]]]], 'rates.0.currency_amounts'],
    'two flat brackets' => ['flat', [['from' => 0, 'to' => 10, 'amount' => 1], ['from' => 10, 'to' => null, 'amount' => 2]], 'rates'],
    'a flat bracket not from 0' => ['flat', [['from' => 1, 'to' => null, 'amount' => 1]], 'rates'],
    'a flat bracket with an end' => ['flat', [['from' => 0, 'to' => 10, 'amount' => 1]], 'rates'],
    'no brackets' => ['flat', [], 'rates'],
]);

it('keeps one method\'s brackets apart in the database too, while two methods may share ranges', function (): void {
    $this->store->run(static function (): void {
        $make = static fn (): ShippingMethod => app(SaveShippingMethod::class)->create(new ShippingMethodDetails(
            ['fulfilment' => 'ship', 'pricing' => 'weight', 'currency' => 'NGN', 'shipping_zone_id' => (int) DB::table('shipping_zones')->value('id')],
            ['name' => ['en' => 'Weight']],
            [['from' => 0, 'to' => 1000, 'amount' => 1, 'currency_amounts' => []]],
        ));
        [$first, $second] = [$make(), $make()];

        expect(static fn () => $first->rates()->create(['range_from' => 500, 'range_to' => null, 'amount' => 1]))->toThrow(QueryException::class)
            ->and(static fn () => $first->rates()->create(['range_from' => 5, 'range_to' => 5, 'amount' => 1]))->toThrow(QueryException::class)
            ->and(static fn () => $first->rates()->create(['range_from' => 2000, 'range_to' => null, 'amount' => -1]))->toThrow(QueryException::class)
            ->and(static fn () => app(SaveShippingMethod::class)->update($first, new ShippingMethodDetails([], [], [['from' => 0, 'to' => null, 'amount' => 1, 'currency_amounts' => []], ['from' => 10, 'to' => 20, 'amount' => 1, 'currency_amounts' => []]])))->toThrow(ShippingRatesOverlapException::class)
            ->and($first->rates()->count())->toBe(1);

        $second->rates()->create(['range_from' => 1000, 'range_to' => null, 'amount' => 1]);
        expect(DB::table('shipping_rates')->count())->toBe(3);
    });
});

it('sets how a method reaches the shopper, and how it is priced, once', function (): void {
    $method = shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Standard'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 100]]])->assertCreated()->json('data.id');

    shippingRequest('PATCH', "/methods/{$method}", ['fulfilment' => 'own_delivery'])->assertUnprocessable()->assertJsonValidationErrors('fulfilment');
    shippingRequest('PATCH', "/methods/{$method}", ['pricing' => 'weight'])->assertUnprocessable()->assertJsonValidationErrors('pricing');
    shippingRequest('PATCH', "/methods/{$method}", ['name' => ['en' => 'Standard shipping'], 'position' => 3, 'is_active' => false, 'free_over' => null])
        ->assertOk()
        ->assertJsonPath('data.name', ['en' => 'Standard shipping'])
        ->assertJsonPath('data.position', 3)
        ->assertJsonPath('data.is_active', false);

    shippingRequest('POST', '/methods', ['name' => ['en' => 'No zone'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 100]]])->assertUnprocessable()->assertJsonValidationErrors('zone');
});

it('offers a pickup at one of the store\'s locations, flat and zoneless, with its address', function (): void {
    $pickup = shippingRequest('POST', '/methods', [
        'name' => ['en' => 'Collect in Ikeja'],
        'fulfilment' => 'pickup',
        'pricing' => 'flat',
        'rates' => [['from' => 0, 'to' => null, 'amount' => 0]],
        'pickup' => ['stock_location' => $this->location, 'country_code' => 'NG', 'state_code' => 'la', 'address_line1' => '1 Allen Avenue', 'city' => 'Ikeja', 'instructions' => ['en' => 'Weekdays, 9 to 5']],
    ])->assertCreated();

    expect($pickup->json('data.pickup'))->toMatchArray(['country_code' => 'NG', 'state_code' => 'LA', 'address_line1' => '1 Allen Avenue', 'city' => 'Ikeja', 'postal_code' => null, 'instructions' => ['en' => 'Weekdays, 9 to 5']])
        ->and($pickup->json('data.zone'))->toBeNull()
        ->and($pickup->json('data.pickup.stock_location.id'))->toBe($this->location);

    $base = ['name' => ['en' => 'Pickup'], 'fulfilment' => 'pickup', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 0]]];
    shippingRequest('POST', '/methods', $base)->assertUnprocessable()->assertJsonValidationErrors('pickup');
    shippingRequest('POST', '/methods', [...$base, 'pricing' => 'weight', 'pickup' => ['stock_location' => $this->location, 'country_code' => 'NG', 'address_line1' => 'A', 'city' => 'B']])->assertUnprocessable()->assertJsonValidationErrors('pricing');
    shippingRequest('POST', '/methods', [...$base, 'zone' => $this->zone, 'free_over' => 100, 'pickup' => ['stock_location' => $this->location, 'country_code' => 'NG', 'address_line1' => 'A', 'city' => 'B']])->assertUnprocessable()->assertJsonValidationErrors(['zone', 'free_over']);
    shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Ship'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 0]], 'pickup' => ['stock_location' => $this->location]])->assertUnprocessable()->assertJsonValidationErrors('pickup');

    // The database holds the same rules.
    $this->store->run(static function (): void {
        expect(static fn () => DB::table('shipping_methods')->where('fulfilment', 'pickup')->update(['shipping_zone_id' => DB::table('shipping_zones')->value('id')]))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('shipping_methods')->where('fulfilment', 'pickup')->update(['pricing' => 'weight']))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('shipping_methods')->where('fulfilment', 'pickup')->update(['pickup_city' => null]))->toThrow(QueryException::class);
    });
});

it('archives methods for good, keeping them for orders, and refuses to delete a zone with live methods', function (): void {
    $method = shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Standard'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 100]]])->assertCreated()->json('data.id');

    shippingRequest('DELETE', "/zones/{$this->zone}")->assertConflict()->assertJsonPath('code', 'shipping_zone_has_methods');

    $archived = shippingRequest('POST', "/methods/{$method}/archive")->assertOk()->json('data.archived_at');
    $this->travel(5)->minutes();
    shippingRequest('POST', "/methods/{$method}/archive")->assertOk()->assertJsonPath('data.archived_at', $archived);
    shippingRequest('PATCH', "/methods/{$method}", ['position' => 1])->assertConflict()->assertJsonPath('code', 'shipping_method_archived');

    shippingRequest('GET', '/methods')->assertJsonPath('meta.total', 0);
    shippingRequest('GET', '/methods?archived=1')->assertJsonPath('meta.total', 1);

    // Once only archived methods are left, the zone goes and they stay, zoneless.
    shippingRequest('DELETE', "/zones/{$this->zone}")->assertNoContent();
    shippingRequest('GET', "/methods/{$method}")->assertOk()->assertJsonPath('data.zone', null)->assertJsonPath('data.rates.0.amount.amount', 100);

    // A live method can never lose its zone.
    $this->store->run(static function (): void {
        $zone = DB::table('shipping_zones')->insertGetId(['public_id' => strtolower((string) Str::ulid()), 'name' => 'Other', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shipping_methods')->update(['archived_at' => null, 'shipping_zone_id' => $zone]);

        expect(static fn () => DB::table('shipping_zones')->where('id', $zone)->delete())->toThrow(QueryException::class);
    });
});

it('audits methods, and each change to their rates as one change with them before and after', function (): void {
    $method = shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Standard'], 'fulfilment' => 'ship', 'pricing' => 'weight', 'rates' => [['from' => 0, 'to' => 1000, 'amount' => 100]]])->assertCreated()->json('data.id');
    shippingRequest('PATCH', "/methods/{$method}", ['rates' => [['from' => 0, 'to' => null, 'amount' => 150, 'currency_amounts' => [['currency' => 'USD', 'amount' => 2]]]], 'currency_amounts' => [['currency' => 'USD', 'free_over' => 90]]])->assertOk();
    shippingRequest('PATCH', "/methods/{$method}", ['rates' => [['from' => 0, 'to' => null, 'amount' => 150, 'currency_amounts' => [['currency' => 'USD', 'amount' => 2]]]]])->assertOk();
    shippingRequest('POST', "/methods/{$method}/archive")->assertOk();

    $audits = $this->store->run(static fn (): array => Audit::query()->where('auditable_type', 'shipping_method')->orderBy('id')->get(['event', 'old_values', 'new_values'])->toArray());

    expect(array_column($audits, 'event'))->toBe(['created', 'rates_replaced', 'rates_replaced', 'updated'])
        ->and($audits[1]['new_values'])->toBe(['rates' => [['from' => 0, 'to' => 1000, 'amount' => 100, 'currency_amounts' => []]], 'free_over_amounts' => []])
        ->and($audits[2])->toMatchArray([
            'old_values' => ['rates' => [['from' => 0, 'to' => 1000, 'amount' => 100, 'currency_amounts' => []]], 'free_over_amounts' => []],
            'new_values' => ['rates' => [['from' => 0, 'to' => null, 'amount' => 150, 'currency_amounts' => ['USD' => 2]]], 'free_over_amounts' => ['USD' => 90]],
        ])
        ->and(array_keys($audits[3]['new_values']))->toBe(['archived_at']);
});

it('locks the base currency and tax mode once a method holds prices in it', function (): void {
    // Nothing else holds prices in this store.
    $this->store->run(static fn (): int => DB::table('store_currencies')->delete());
    expect($this->store->run(static fn (): bool => app(PricedRecordsRegistry::class)->anyExist()))->toBeFalse();

    shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Standard'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 100]]])->assertCreated();

    expect($this->store->run(static fn (): bool => app(PricedRecordsRegistry::class)->anyExist()))->toBeTrue();
});

it('lets staff with shipping.view see methods but not change them', function (): void {
    $method = shippingRequest('POST', '/methods', ['zone' => $this->zone, 'name' => ['en' => 'Standard'], 'fulfilment' => 'ship', 'pricing' => 'flat', 'rates' => [['from' => 0, 'to' => null, 'amount' => 100]]])->assertCreated()->json('data.id');

    shippingRequest('GET', '/methods', as: $this->viewer)->assertOk();
    shippingRequest('POST', '/methods', ['name' => ['en' => 'More']], as: $this->viewer)->assertForbidden();
    shippingRequest('PATCH', "/methods/{$method}", ['position' => 2], as: $this->viewer)->assertForbidden();
    shippingRequest('POST', "/methods/{$method}/archive", as: $this->viewer)->assertForbidden();
});
