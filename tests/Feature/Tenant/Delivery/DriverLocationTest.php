<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Actions\RecordDriverLocation;
use App\Tenant\Delivery\DriverLocationRetention;
use App\Tenant\Delivery\Exceptions\DeliveryNotUnderWayException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DriverLocation;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 10, Step 17: driver location is personal data. It is taken only
 * while a delivery is picked up or on the way, only from its own driver, and
 * never kept after it ends. Staff see the trail; the order's buyer (customer,
 * or guest with the order token in a header) sees only the driver's first
 * name and latest point, only while the delivery is on the way, never proof.
 * Purged 30 days after the delivery ends.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['store' => $this->store, 'owner' => $this->owner, 'driver' => $this->driver, 'token' => $this->token] = deliveryStore();
});

afterEach(function (): void {
    deleteAllStores();
});

it('takes the driver\'s location only while their delivery is picked up or on the way', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;

    sendLocation($delivery, $this->token)->assertConflict()->assertJsonPath('code', 'delivery_not_under_way');

    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();
    sendLocation($delivery, $this->token, 6.4541, 3.3947, 12)->assertCreated()->assertJson(['data' => ['latitude' => 6.4541, 'longitude' => 3.3947, 'accuracy_meters' => 12]]);
    driverAppRequest('POST', "deliveries/{$delivery}/on-the-way", token: $this->token)->assertOk();
    sendLocation($delivery, $this->token, 6.46, 3.4, null)->assertCreated()->assertJsonPath('data.accuracy_meters', null);
    driverAppRequest('POST', "deliveries/{$delivery}/delivered", ['recipient_name' => 'Ada Obi', 'photo' => proofPhoto()], $this->token, headers: ['Idempotency-Key' => 'delivered-location'])->assertOk();
    sendLocation($delivery, $this->token)->assertConflict()->assertJsonPath('code', 'delivery_not_under_way');

    sendLocation($delivery, $this->token, 91.0)->assertUnprocessable()->assertJsonValidationErrors('latitude');
    sendLocation($delivery, $this->token, 6.0, -181.0)->assertUnprocessable()->assertJsonValidationErrors('longitude');

    expect($this->store->run(fn (): array => DriverLocation::query()->orderBy('id')->get()->map(fn (DriverLocation $location): array => [$location->driver_id, $location->latitude, $location->longitude])->all()))
        ->toBe([[$this->driver->id, '6.454100', '3.394700'], [$this->driver->id, '6.460000', '3.400000']]);
});

it('never takes a location for another driver\'s delivery', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();
    $other = $this->store->run(static fn () => activeDriver('+2348098765432', 'Bisi Rider'));

    sendLocation($delivery, driverTokenFor($other, $this->store))->assertNotFound();
    sendLocation($delivery, null)->assertUnauthorized();

    expect($this->store->run(static fn (): int => DriverLocation::query()->count()))->toBe(0);
});

it('waits for a status change under way, and refuses the point once the delivery is no longer under way', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();

    $this->store->run(static function () use ($delivery): void {
        $model = Delivery::query()->where('public_id', $delivery)->sole();
        $record = static fn (): DriverLocation => app(RecordDriverLocation::class)->handle($model, '6.45', '3.39', null);

        // Another request is failing the delivery: it holds the row, as a status change does.
        $failing = SecondConnection::open()->holdRowLock($model);
        $failing->run(static fn () => DB::connection(SecondConnection::NAME)->table('deliveries')->where('id', $model->id)->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'refused']));

        expect($failing->blockedStatement($record))->toContain('for share');

        $failing->commit();

        expect($record)->toThrow(DeliveryNotUnderWayException::class)
            ->and(DriverLocation::query()->count())->toBe(0);
    });
});

it('shows staff who can view deliveries the whole trail, oldest first', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();
    sendLocation($delivery, $this->token, 6.1, 3.1)->assertCreated();
    sendLocation($delivery, $this->token, 6.2, 3.2)->assertCreated();
    sendLocation($delivery, $this->token, 6.3, 3.3)->assertCreated();
    $viewer = deliveryStaffWith($this->store, ['delivery.view']);

    $first = deliveryRequest('GET', "deliveries/{$delivery}/locations?per_page=2", as: $viewer)->assertOk();
    expect(array_column($first->json('data'), 'latitude'))->toBe([6.1, 6.2]);
    $next = deliveryRequest('GET', "deliveries/{$delivery}/locations?per_page=2&cursor=".$first->json('meta.next_cursor'), as: $viewer)->assertOk();
    expect(array_column($next->json('data'), 'latitude'))->toBe([6.3]);

    deliveryRequest('GET', "deliveries/{$delivery}/locations", as: deliveryStaffWith($this->store, ['delivery.manage']))->assertForbidden();
    $this->withToken($this->token)->getJson(storeUrl('first-store', "/api/v1/staff/delivery/deliveries/{$delivery}/locations"))->assertUnauthorized();
});

it('shows the customer only the driver\'s first name and latest point, only while the delivery is on the way', function (): void {
    $customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $delivery = customerOwnDelivery($this->store, $this->owner, $this->driver, $customer);
    $order = $this->store->run(static fn (): string => $delivery->shipment->order->public_id);
    $track = static fn (Customer $as) => customerRequest('GET', "orders/{$order}/delivery-tracking", as: $as);

    $track($customer)->assertOk()->assertJsonPath('data.order.id', $order)->assertJsonPath('data.deliveries', []);

    driverAppRequest('POST', "deliveries/{$delivery->public_id}/pick-up", token: $this->token)->assertOk();
    sendLocation($delivery->public_id, $this->token, 6.1, 3.1)->assertCreated();
    // Picked up but not yet on the way: not shown.
    $track($customer)->assertOk()->assertJsonPath('data.deliveries', []);

    driverAppRequest('POST', "deliveries/{$delivery->public_id}/on-the-way", token: $this->token)->assertOk();
    sendLocation($delivery->public_id, $this->token, 6.2, 3.2, 8)->assertCreated();
    $shown = $track($customer)->assertOk()->json('data.deliveries');

    expect($shown)->toHaveCount(1)
        ->and(array_keys($shown[0]))->toBe(['shipment', 'driver', 'location'])
        ->and($shown[0]['driver'])->toBe(['first_name' => 'Dayo'])
        ->and(array_keys($shown[0]['location']))->toBe(['latitude', 'longitude', 'accuracy_meters', 'recorded_at'])
        ->and([$shown[0]['location']['latitude'], $shown[0]['location']['longitude'], $shown[0]['location']['accuracy_meters']])->toBe([6.2, 3.2, 8]);

    $someoneElse = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $track($someoneElse)->assertNotFound();
    $this->withoutToken();
    customerRequest('GET', "orders/{$order}/delivery-tracking")->assertUnauthorized();

    driverAppRequest('POST', "deliveries/{$delivery->public_id}/delivered", ['recipient_name' => 'Ada Obi', 'photo' => proofPhoto()], $this->token, headers: ['Idempotency-Key' => 'delivered-tracked'])->assertOk();
    $track($customer)->assertOk()->assertJsonPath('data.deliveries', []);
});

it('lets a guest follow their order with its token in a header, never in the URL', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    $order = $this->store->run(static fn () => Delivery::query()->sole()->shipment->order);
    $token = guestOrderToken($order);
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$delivery}/on-the-way", token: $this->token)->assertOk();

    // On the way, before the app has sent a position.
    guestOrderRequest('GET', "{$order->public_id}/delivery-tracking", $token, subdomain: 'first-store')
        ->assertOk()->assertJsonPath('data.deliveries.0.driver.first_name', 'Dayo')->assertJsonPath('data.deliveries.0.location', null);

    guestOrderRequest('GET', "{$order->public_id}/delivery-tracking", null, subdomain: 'first-store')->assertNotFound();
    // A token in the URL is refused outright, before it is ever looked at.
    guestOrderRequest('GET', "{$order->public_id}/delivery-tracking?token={$token}", null, subdomain: 'first-store')->assertBadRequest()->assertJsonPath('code', 'secret_in_url');
    guestOrderRequest('GET', "{$order->public_id}/delivery-tracking", 'not-the-token', subdomain: 'first-store')->assertNotFound();
});

it('limits how often a driver sends a location and how often a buyer polls', function (): void {
    config(['api.rate_limits.driver_location' => 2, 'api.rate_limits.delivery_tracking' => 2]);
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();

    sendLocation($delivery, $this->token)->assertCreated();
    sendLocation($delivery, $this->token)->assertCreated();
    sendLocation($delivery, $this->token)->assertTooManyRequests();

    $order = $this->store->run(static fn () => Delivery::query()->sole()->shipment->order);
    $token = guestOrderToken($order);
    $poll = static fn (string $ip) => guestOrderRequest('GET', "{$order->public_id}/delivery-tracking", $token, ip: $ip, subdomain: 'first-store');
    $poll('203.0.113.1')->assertOk();
    $poll('203.0.113.2')->assertOk();
    // Counted per order token, wherever the guest polls from.
    $poll('203.0.113.3')->assertTooManyRequests();
});

it('purges locations 30 days after their delivery ends, keeping those of deliveries still under way', function (): void {
    $this->travelTo(now()->subDays(31));
    $old = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$old}/pick-up", token: $this->token)->assertOk();
    sendLocation($old, $this->token)->assertCreated();
    driverAppRequest('POST', "deliveries/{$old}/delivered", ['recipient_name' => 'Ada Obi', 'photo' => proofPhoto()], $this->token, headers: ['Idempotency-Key' => 'delivered-old'])->assertOk();
    $stillOut = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$stillOut}/pick-up", token: $this->token)->assertOk();
    sendLocation($stillOut, $this->token)->assertCreated();
    $this->travelBack();

    $purged = $this->store->run(static fn (): int => app(DriverLocationRetention::class)->purgeOlderThan(now()->subDays(config()->integer('retention.periods.driver_locations'))->toImmutable()));

    expect($purged)->toBe(1)
        ->and($this->store->run(static fn (): array => DriverLocation::query()->with('delivery')->get()->map(static fn (DriverLocation $location): string => $location->delivery->public_id)->all()))->toBe([$stillOut]);
});
