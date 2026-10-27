<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Delivery\Actions\AssignDriver;
use App\Tenant\Delivery\Actions\MoveDelivery;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Exceptions\ShipmentCannotBeAssignedException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Exceptions\ShipmentWithDriverException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 17: staff give the store's own deliveries to drivers, who move them
 * along: picked up (the stock leaves the shelf), on the way, delivered (with
 * who took the goods) or failed (with why). A failed delivery stays with the
 * driver until staff send it out again or take the goods back. A shipment
 * has at most one delivery under way, staff can't ship it behind the
 * driver's back, and a driver who has goods can't be deactivated or erased.
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

it('takes a delivery from assigned to delivered, moving its shipment and stock along, and keeps its history', function (): void {
    $shipment = $this->store->run(fn (): string => pendingOwnDelivery($this->owner)->public_id);

    $delivery = deliveryRequest('POST', "shipments/{$shipment}/deliveries", ['driver' => $this->driver->public_id], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.attempt', 1)
        ->assertJsonPath('data.driver.id', $this->driver->public_id)
        ->json('data.id');

    // The driver sees where to take it, what it holds and who to call.
    $seen = driverAppRequest('GET', 'deliveries', token: $this->token)->assertOk()->json('data.0');
    expect($seen['id'])->toBe($delivery)
        ->and($seen['recipient']['line1'])->toBe('5 Allen Avenue')
        ->and($seen['items'][0]['quantity'])->toBe(2)
        ->and($seen['cash_to_collect'])->toBeNull();

    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk()->assertJsonPath('data.status', 'picked_up');
    expect($this->store->run(static fn (): array => [Shipment::query()->sole()->status->value, StockItem::query()->sole()->on_hand]))->toBe(['shipped', 3]);

    driverAppRequest('POST', "deliveries/{$delivery}/on-the-way", token: $this->token)->assertOk()->assertJsonPath('data.status', 'on_the_way');
    driverAppRequest('POST', "deliveries/{$delivery}/delivered", ['recipient_name' => ''], $this->token, headers: ['Idempotency-Key' => 'delivery-key-1'])->assertUnprocessable()->assertJsonValidationErrors('recipient_name');
    driverAppRequest('POST', "deliveries/{$delivery}/delivered", ['recipient_name' => 'Ada Obi'], $this->token, headers: ['Idempotency-Key' => 'delivery-key-2'])
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        // Delivered: the driver no longer sees the address.
        ->assertJsonPath('data.recipient', null);

    expect($this->store->run(static fn (): array => [Shipment::query()->sole()->status->value, Order::query()->sole()->status->value]))->toBe(['delivered', 'completed']);

    $shown = deliveryRequest('GET', "deliveries/{$delivery}", as: $this->owner)->assertOk()->json('data');
    expect($shown['recipient_name'])->toBe('Ada Obi')
        ->and(array_column($shown['status_changes'], 'to'))->toBe(['assigned', 'picked_up', 'on_the_way', 'delivered'])
        ->and($shown['status_changes'][1]['by_type'])->toBe('driver')
        ->and($shown['status_changes'][0]['by_type'])->toBe('staff_member');
});

it('lets staff ship an own delivery themselves only while no driver has it', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        $withDriver = pendingOwnDelivery($this->owner);
        app(AssignDriver::class)->handle($this->owner, $withDriver, $this->driver);

        expect(static fn () => app(MoveShipment::class)->ship($withDriver, $staff))->toThrow(ShipmentWithDriverException::class)
            ->and($withDriver->refresh()->status->value)->toBe('pending');

        $withoutDriver = pendingOwnDelivery($this->owner);
        app(MoveShipment::class)->deliver(app(MoveShipment::class)->ship($withoutDriver, $staff), $staff);
        expect($withoutDriver->refresh()->status->value)->toBe('delivered');
    });
});

it('gives a driver only an own delivery that hasn\'t left, one at a time, and only an active driver', function (): void {
    [$shipment, $carrier, $inactive] = $this->store->run(function (): array {
        $carrierOrder = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod())))->load('lines');
        $carrier = app(CreateShipment::class)->handle($carrierOrder, [$carrierOrder->lines[0]->public_id => 1], AccountReference::to($this->owner), 'TRK');
        $inactive = activeDriver('+2348099999999', 'Off Duty');
        $inactive->forceFill(['is_active' => false])->save();

        return [pendingOwnDelivery($this->owner)->public_id, $carrier->public_id, $inactive->public_id];
    });

    deliveryRequest('POST', "shipments/{$carrier}/deliveries", ['driver' => $this->driver->public_id], $this->owner)->assertConflict()->assertJsonPath('code', 'shipment_cannot_be_assigned');
    deliveryRequest('POST', "shipments/{$shipment}/deliveries", ['driver' => $inactive], $this->owner)->assertConflict()->assertJsonPath('code', 'driver_deactivated');
    deliveryRequest('POST', "shipments/{$shipment}/deliveries", ['driver' => 'nobody'], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('driver');
    deliveryRequest('POST', "shipments/{$shipment}/deliveries", ['driver' => $this->driver->public_id], $this->owner)->assertCreated();
    deliveryRequest('POST', "shipments/{$shipment}/deliveries", ['driver' => $this->driver->public_id], $this->owner)->assertConflict()->assertJsonPath('code', 'shipment_cannot_be_assigned');
});

it('moves a delivery only one step at a time, and asks why when it fails', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;

    driverAppRequest('POST', "deliveries/{$delivery}/delivered", ['recipient_name' => 'Ada Obi'], $this->token, headers: ['Idempotency-Key' => 'delivery-key-3'])->assertConflict()->assertJsonPath('code', 'delivery_transition_not_allowed');
    driverAppRequest('POST', "deliveries/{$delivery}/failed", ['reason' => 'refused'], $this->token)->assertConflict();
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertConflict();
    driverAppRequest('POST', "deliveries/{$delivery}/failed", ['reason' => 'lost'], $this->token)->assertUnprocessable()->assertJsonValidationErrors('reason');
    driverAppRequest('POST', "deliveries/{$delivery}/failed", ['reason' => 'other'], $this->token)->assertUnprocessable()->assertJsonValidationErrors('note');
    driverAppRequest('POST', "deliveries/{$delivery}/failed", ['reason' => 'recipient_asked_to_reschedule'], $this->token)
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'recipient_asked_to_reschedule');

    // Once picked up, the goods are with the driver: it can't be cancelled or given to someone else.
    deliveryRequest('POST', "deliveries/{$delivery}/cancel", as: $this->owner)->assertConflict();
    $other = $this->store->run(static fn (): string => activeDriver('+2348099999999', 'Bola')->public_id);
    deliveryRequest('POST', "deliveries/{$delivery}/reassign", ['driver' => $other], $this->owner)->assertConflict();
});

it('sends a failed delivery out again with the same driver, who still has the goods', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$delivery}/failed", ['reason' => 'recipient_not_available'], $this->token)->assertOk();

    $retry = deliveryRequest('POST', "deliveries/{$delivery}/retry", as: $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.attempt', 2)
        ->assertJsonPath('data.status', 'picked_up')
        ->json('data.id');
    deliveryRequest('POST', "deliveries/{$delivery}/retry", as: $this->owner)->assertConflict();
    deliveryRequest('GET', "deliveries/{$delivery}", as: $this->owner)->assertJsonPath('data.resolution', 'retried')->assertJsonPath('data.holds_goods', false);

    driverAppRequest('POST', "deliveries/{$retry}/delivered", ['recipient_name' => 'Ada Obi'], $this->token, headers: ['Idempotency-Key' => 'delivery-key-4'])->assertOk();
    expect($this->store->run(static fn (): string => Shipment::query()->sole()->status->value))->toBe('delivered');
});

it('takes a failed delivery\'s goods back to the store, so the order can ship again with another driver', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$delivery}/failed", ['reason' => 'address_not_found'], $this->token)->assertOk();

    deliveryRequest('POST', "deliveries/{$delivery}/return", as: $this->owner)->assertOk()->assertJsonPath('data.resolution', 'returned');

    $this->store->run(function (): void {
        expect(Shipment::query()->sole()->status->value)->toBe('returned')
            ->and(StockItem::query()->sole()->only(['on_hand', 'reserved']))->toBe(['on_hand' => 5, 'reserved' => 2]);

        $order = Order::query()->sole()->load('lines');
        $again = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 2], AccountReference::to($this->owner));
        $other = activeDriver('+2348099999999', 'Bola');

        expect(app(AssignDriver::class)->handle($this->owner, $again, $other)->attempt)->toBe(1);
    });
});

it('takes an assigned delivery back when its shipment or order is cancelled', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver);

    $this->store->run(fn (): Order => app(CancelOrder::class)->handle(Order::query()->sole(), CancellationReason::Staff, AccountReference::to($this->owner)));

    deliveryRequest('GET', "deliveries/{$delivery->public_id}", as: $this->owner)->assertJsonPath('data.status', 'cancelled');
    driverAppRequest('POST', "deliveries/{$delivery->public_id}/pick-up", token: $this->token)->assertConflict();
});

it('refuses to deactivate or erase a driver who has goods, and takes back what they haven\'t picked up', function (): void {
    $holding = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    $waiting = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$holding}/pick-up", token: $this->token)->assertOk();

    deliveryRequest('POST', "drivers/{$this->driver->public_id}/deactivate", as: $this->owner)->assertConflict()->assertJsonPath('code', 'driver_holds_goods');
    deliveryRequest('POST', "drivers/{$this->driver->public_id}/erasure", ['password' => DELIVERY_STAFF_PASSWORD], $this->owner)->assertConflict()->assertJsonPath('code', 'driver_holds_goods');

    driverAppRequest('POST', "deliveries/{$holding}/delivered", ['recipient_name' => 'Ada Obi'], $this->token, headers: ['Idempotency-Key' => 'delivery-key-5'])->assertOk();
    deliveryRequest('POST', "drivers/{$this->driver->public_id}/deactivate", as: $this->owner)->assertOk();

    deliveryRequest('GET', "deliveries/{$waiting}", as: $this->owner)->assertJsonPath('data.status', 'cancelled');
});

it('shows a driver only their own deliveries, and staff only with delivery.view, changing them only with delivery.manage', function (): void {
    $mine = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    $other = $this->store->run(static fn (): Driver => activeDriver('+2348099999999', 'Bola'));
    $theirs = assignedDelivery($this->store, $this->owner, $other)->public_id;

    expect(driverAppRequest('GET', 'deliveries', token: $this->token)->json('data.*.id'))->toBe([$mine]);
    driverAppRequest('GET', "deliveries/{$theirs}", token: $this->token)->assertNotFound();
    driverAppRequest('POST', "deliveries/{$theirs}/pick-up", token: $this->token)->assertNotFound();
    driverAppRequest('GET', 'deliveries')->assertUnauthorized();

    $viewer = deliveryStaffWith($this->store, ['delivery.view']);
    $nobody = deliveryStaffWith($this->store, []);
    deliveryRequest('GET', 'deliveries', as: $viewer)->assertOk()->assertJsonCount(2, 'data');
    deliveryRequest('GET', 'deliveries?driver='.$other->public_id, as: $viewer)->assertOk()->assertJsonPath('data.0.id', $theirs)->assertJsonCount(1, 'data');
    deliveryRequest('GET', "deliveries/{$mine}", as: $viewer)->assertOk();
    deliveryRequest('POST', "deliveries/{$mine}/cancel", as: $viewer)->assertForbidden();
    deliveryRequest('POST', "deliveries/{$mine}/status", ['status' => 'picked_up'], $viewer, headers: ['Idempotency-Key' => 'delivery-key-6'])->assertForbidden();
    deliveryRequest('GET', 'deliveries', as: $nobody)->assertForbidden();
    deliveryRequest('GET', 'deliveries')->assertUnauthorized();

    // A driver's token is no staff token.
    test()->withToken($this->token)->getJson(storeUrl('first-store', '/api/v1/staff/delivery/deliveries'))->assertUnauthorized();
});

it('lets staff move a delivery for a driver who can\'t use the app, recorded as done by them', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    $manager = deliveryStaffWith($this->store, ['delivery.manage']);

    deliveryRequest('POST', "deliveries/{$delivery}/status", ['status' => 'cancelled'], $manager, headers: ['Idempotency-Key' => 'delivery-key-7'])->assertUnprocessable()->assertJsonValidationErrors('status');
    deliveryRequest('POST', "deliveries/{$delivery}/status", ['status' => 'picked_up'], $manager, headers: ['Idempotency-Key' => 'delivery-key-8'])->assertOk();
    deliveryRequest('POST', "deliveries/{$delivery}/status", ['status' => 'delivered'], $manager, headers: ['Idempotency-Key' => 'delivery-key-9'])->assertUnprocessable()->assertJsonValidationErrors('recipient_name');
    deliveryRequest('POST', "deliveries/{$delivery}/status", ['status' => 'delivered', 'recipient_name' => 'Ada Obi'], $manager, headers: ['Idempotency-Key' => 'delivery-key-10'])->assertOk()->assertJsonPath('data.status', 'delivered');

    $changes = deliveryRequest('GET', "deliveries/{$delivery}", as: $this->owner)->json('data.status_changes');
    expect($changes[2]['by_id'])->toBe($manager->public_id);
});

it('lets staff give an assigned delivery to another driver, or cancel it', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    $other = $this->store->run(static fn (): string => activeDriver('+2348099999999', 'Bola')->public_id);

    deliveryRequest('POST', "deliveries/{$delivery}/reassign", ['driver' => $this->driver->public_id], $this->owner)->assertConflict();
    $new = deliveryRequest('POST', "deliveries/{$delivery}/reassign", ['driver' => $other], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.driver.id', $other)
        ->assertJsonPath('data.attempt', 2)
        ->json('data.id');

    deliveryRequest('GET', "deliveries/{$delivery}", as: $this->owner)->assertJsonPath('data.status', 'cancelled');
    deliveryRequest('POST', "deliveries/{$new}/cancel", as: $this->owner)->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('never shows a delivery to another store', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    ['owner' => $otherOwner, 'token' => $otherToken] = deliveryStore('second-store');

    deliveryRequest('GET', "deliveries/{$delivery}", as: $otherOwner, subdomain: 'second-store')->assertNotFound();
    driverAppRequest('GET', "deliveries/{$delivery}", token: $otherToken, subdomain: 'second-store')->assertNotFound();
});

it('gives a shipment one delivery when two staff assign it at the same moment', function (): void {
    $this->store->run(function (): void {
        $shipment = pendingOwnDelivery($this->owner);
        $other = activeDriver('+2348099999999', 'Bola');

        // Another request holds the order and has assigned the shipment, not yet committed.
        $second = SecondConnection::open()->holdRowLock($shipment->order);
        $second->run(function () use ($shipment, $other): void {
            DB::connection(SecondConnection::NAME)->table('deliveries')->insert([
                'public_id' => strtolower((string) Illuminate\Support\Str::ulid()), 'shipment_id' => $shipment->id, 'driver_id' => $other->id, 'attempt' => 1,
                'status' => 'assigned', 'assigned_at' => now(), 'assigned_by_type' => 'staff_member', 'assigned_by_id' => $this->owner->public_id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $assign = fn (): Delivery => app(AssignDriver::class)->handle($this->owner, $shipment, $this->driver);
        expect($second->blockedStatement($assign))->toContain('for update');
        $second->commit();

        expect($assign)->toThrow(ShipmentCannotBeAssignedException::class)
            ->and(Delivery::query()->count())->toBe(1);
    });
});

it('moves a delivery once when its driver taps twice at the same moment', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver);

    $this->store->run(function () use ($delivery): void {
        // The first tap holds the delivery and has picked it up, not yet committed.
        $first = SecondConnection::open()->holdRowLock($delivery);
        $first->run(static fn () => DB::connection(SecondConnection::NAME)->table('deliveries')->where('id', $delivery->id)->update(['status' => 'picked_up', 'picked_up_at' => now()]));

        $tap = fn (): Delivery => app(MoveDelivery::class)->pickUp($delivery, AccountReference::to($this->driver));
        expect($first->blockedStatement($tap))->toContain('for update');
        $first->commit();

        expect($tap)->toThrow(DeliveryTransitionNotAllowedException::class)
            ->and(StockItem::query()->sole()->on_hand)->toBe(5);
    });
});

it('makes deactivating a driver wait for an assignment to them under way, so nothing is left assigned to a deactivated driver', function (): void {
    $this->store->run(function (): void {
        $shipment = pendingOwnDelivery($this->owner);

        // Staff are deactivating the driver: they hold the driver, not yet committed.
        $deactivating = SecondConnection::open()->holdRowLock($this->driver);
        $deactivating->run(fn () => DB::connection(SecondConnection::NAME)->table('drivers')->where('id', $this->driver->id)->update(['is_active' => false]));

        $assign = fn (): Delivery => app(AssignDriver::class)->handle($this->owner, $shipment, $this->driver);
        expect($deactivating->blockedStatement($assign))->toContain('for share');
        $deactivating->commit();

        expect($assign)->toThrow(App\Tenant\Delivery\Exceptions\DriverDeactivatedException::class)
            ->and(Delivery::query()->count())->toBe(0);
    });
});

it('keeps every delivery\'s history as it happened, and the database allows one delivery under way per shipment', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver);

    $this->store->run(function () use ($delivery): void {
        expect(static fn () => DB::table('delivery_status_changes')->update(['to_status' => 'delivered']))->toThrow(QueryException::class, 'append-only')
            ->and(static fn () => DB::table('delivery_status_changes')->delete())->toThrow(QueryException::class, 'append-only')
            ->and(fn () => DB::table('deliveries')->insert([
                'public_id' => strtolower((string) Illuminate\Support\Str::ulid()), 'shipment_id' => $delivery->shipment_id, 'driver_id' => $this->driver->id, 'attempt' => 2,
                'status' => 'assigned', 'assigned_at' => now(), 'assigned_by_type' => 'staff_member', 'assigned_by_id' => 'x', 'created_at' => now(), 'updated_at' => now(),
            ]))->toThrow(QueryException::class, 'deliveries_one_open_per_shipment')
            ->and(static fn () => DB::table('deliveries')->where('id', $delivery->id)->update(['status' => 'delivered', 'delivered_at' => now(), 'picked_up_at' => now()]))->toThrow(QueryException::class, 'deliveries_delivered');
    });
});
