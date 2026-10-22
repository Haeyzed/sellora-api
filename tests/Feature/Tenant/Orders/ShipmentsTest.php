<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Events\OrderCompleted;
use App\Tenant\Orders\Events\OrderFulfilled;
use App\Tenant\Orders\Events\ShipmentCreated;
use App\Tenant\Orders\Exceptions\OrderCannotBeCancelledException;
use App\Tenant\Orders\Exceptions\ShipmentQuantityTooHighException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 14e: shipments. Staff ship a paid order, in parts if they like; a
 * line never ships more than was bought, checked under the order's lock.
 * Stock leaves the shelf when a shipment ships, or when a pickup is
 * collected. The order's fulfilment follows its shipments, and it completes
 * once paid and fulfilled. Delivery (Step 17) picks up the store's own
 * deliveries from ShipmentCreated.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
});

afterEach(function (): void {
    deleteAllStores();
});

it('ships a paid order in parts, taking stock off the shelf as each part ships, and completes it once everything has', function (): void {
    Event::fake([ShipmentCreated::class, OrderFulfilled::class, OrderCompleted::class]);
    [$order, $shirt, $lamp] = $this->store->run(static function (): array {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5, name: 'Shirt'), 3], [cartVariant(5000, stock: 5, name: 'Lamp'), 1]], orderShippingMethod())))->load('lines');

        return [$order->public_id, $order->lines[0]->public_id, $order->lines[1]->public_id];
    });
    $levels = fn (): array => $this->store->run(static fn (): array => StockItem::query()->orderBy('product_variant_id')->get()->map->only(['on_hand', 'reserved'])->all());

    $first = staffOrderRequest('POST', "/orders/{$order}/shipments", ['lines' => [['line' => $shirt, 'quantity' => 2]], 'tracking_number' => 'TRK-1', 'tracking_url' => 'https://track.example.com/TRK-1'], headers: ['Idempotency-Key' => 'shipment-key-ship-1'])->assertCreated();
    expect($first->json('data'))->toMatchArray(['fulfilment' => 'ship', 'status' => 'pending', 'tracking_number' => 'TRK-1', 'lines' => [['line' => $shirt, 'quantity' => 2]]])
        // Nothing has left yet.
        ->and($levels())->toBe([['on_hand' => 5, 'reserved' => 3], ['on_hand' => 5, 'reserved' => 1]]);
    $rest = staffOrderRequest('POST', "/orders/{$order}/shipments", ['lines' => [['line' => $shirt, 'quantity' => 1], ['line' => $lamp, 'quantity' => 1]], 'tracking_number' => 'TRK-2'], headers: ['Idempotency-Key' => 'shipment-key-ship-2'])->assertCreated()->json('data.id');

    // Only what has left counts: the rest is still waiting to go.
    staffOrderRequest('POST', '/shipments/'.$first->json('data.id').'/ship')->assertOk()->assertJsonPath('data.status', 'shipped');
    expect($levels())->toBe([['on_hand' => 3, 'reserved' => 1], ['on_hand' => 5, 'reserved' => 1]])
        ->and(staffOrderRequest('GET', "/orders/{$order}")->json('data.fulfilment_status'))->toBe('partially_fulfilled');

    staffOrderRequest('POST', "/shipments/{$rest}/ship")->assertOk();
    expect($levels())->toBe([['on_hand' => 2, 'reserved' => 0], ['on_hand' => 4, 'reserved' => 0]]);

    $shown = staffOrderRequest('GET', "/orders/{$order}")->json('data');
    expect([$shown['fulfilment_status'], $shown['status']])->toBe(['fulfilled', 'completed'])
        ->and($shown['fulfilled_at'])->not->toBeNull()
        ->and(staffOrderRequest('GET', "/orders/{$order}/events")->json('data.*.type'))->toBe(['placed', 'paid', 'invoice_issued', 'shipment_created', 'shipment_created', 'shipment_shipped', 'shipment_shipped', 'fulfilled', 'completed']);

    staffOrderRequest('POST', "/shipments/{$rest}/deliver")->assertOk()->assertJsonPath('data.status', 'delivered');
    Event::assertDispatchedTimes(ShipmentCreated::class, 2);
    Event::assertDispatchedTimes(OrderFulfilled::class, 1);
    Event::assertDispatchedTimes(OrderCompleted::class, 1);
});

it('never ships more of a line than was bought, a line that doesn\'t ship, or an order that isn\'t paid and open', function (): void {
    [$unpaid, $paid, $line, $service] = $this->store->run(static function (): array {
        $method = orderShippingMethod();
        $giftWrap = cartVariant(500);
        $giftWrap->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();
        $paid = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2], [$giftWrap, 1]], $method)))->load('lines');

        return [placeOrder(orderDraft([[cartVariant(10000), 1]], $method))->public_id, $paid->public_id, $paid->lines[0]->public_id, $paid->lines[1]->public_id];
    });

    staffOrderRequest('POST', "/orders/{$unpaid}/shipments", ['lines' => [['line' => 'x', 'quantity' => 1]]], headers: ['Idempotency-Key' => 'shipment-key-a'])->assertConflict()->assertJsonPath('code', 'order_cannot_be_fulfilled');
    staffOrderRequest('POST', "/orders/{$paid}/shipments", ['lines' => [['line' => $line, 'quantity' => 3]]], headers: ['Idempotency-Key' => 'shipment-key-b'])->assertUnprocessable()->assertJsonPath('code', 'shipment_quantity_too_high')->assertJsonValidationErrors("lines.{$line}");
    staffOrderRequest('POST', "/orders/{$paid}/shipments", ['lines' => [['line' => $service, 'quantity' => 1]]], headers: ['Idempotency-Key' => 'shipment-key-c'])->assertUnprocessable()->assertJsonPath('code', 'shipment_quantity_too_high');
    staffOrderRequest('POST', "/orders/{$paid}/shipments", ['lines' => [['line' => $line, 'quantity' => 2]]], headers: ['Idempotency-Key' => 'shipment-key-d'])->assertCreated();
    staffOrderRequest('POST', "/orders/{$paid}/shipments", ['lines' => [['line' => $line, 'quantity' => 1]]], headers: ['Idempotency-Key' => 'shipment-key-e'])->assertUnprocessable()->assertJsonPath('code', 'shipment_quantity_too_high');

    // Without an Idempotency-Key, nothing is created.
    staffOrderRequest('POST', "/orders/{$paid}/shipments", ['lines' => [['line' => $line, 'quantity' => 1]]])->assertBadRequest()->assertJsonPath('code', 'idempotency_key_missing');
});

it('lets a cancelled shipment\'s lines ship again, and only a shipment that hasn\'t left be cancelled', function (): void {
    [$order, $line] = $this->store->run(static function (): array {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], orderShippingMethod())))->load('lines');

        return [$order->public_id, $order->lines[0]->public_id];
    });

    $first = staffOrderRequest('POST', "/orders/{$order}/shipments", ['lines' => [['line' => $line, 'quantity' => 2]], 'tracking_number' => 'T'], headers: ['Idempotency-Key' => 'shipment-key-1'])->json('data.id');
    staffOrderRequest('POST', "/shipments/{$first}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    staffOrderRequest('POST', "/shipments/{$first}/ship")->assertConflict()->assertJsonPath('code', 'shipment_transition_not_allowed');

    $second = staffOrderRequest('POST', "/orders/{$order}/shipments", ['lines' => [['line' => $line, 'quantity' => 2]]], headers: ['Idempotency-Key' => 'shipment-key-2'])->assertCreated()->json('data.id');
    // A carrier shipment needs its tracking number before it ships.
    staffOrderRequest('POST', "/shipments/{$second}/ship")->assertUnprocessable()->assertJsonPath('code', 'tracking_number_required')->assertJsonValidationErrors('tracking_number');
    staffOrderRequest('PATCH', "/shipments/{$second}", ['tracking_number' => 'TRK-9', 'tracking_url' => 'http://insecure.example.com'])->assertJsonValidationErrors('tracking_url');
    staffOrderRequest('PATCH', "/shipments/{$second}", ['tracking_number' => 'TRK-9', 'tracking_url' => 'https://track.example.com/9'])->assertOk()->assertJsonPath('data.tracking_number', 'TRK-9');
    staffOrderRequest('POST', "/shipments/{$second}/ship")->assertOk();
    staffOrderRequest('POST', "/shipments/{$second}/cancel")->assertConflict();
    staffOrderRequest('POST', "/shipments/{$second}/deliver")->assertOk();
    staffOrderRequest('PATCH', "/shipments/{$second}", ['tracking_number' => 'X', 'tracking_url' => null])->assertConflict()->assertJsonPath('code', 'shipment_closed');
});

it('readies a pickup and takes its stock off the shelf only when it is collected', function (): void {
    [$order, $line] = $this->store->run(static function (): array {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(ShippingFulfilment::Pickup, 'Collect'), 0)))->load('lines');

        return [$order->public_id, $order->lines[0]->public_id];
    });

    $pickup = staffOrderRequest('POST', "/orders/{$order}/shipments", ['lines' => [['line' => $line, 'quantity' => 1]]], headers: ['Idempotency-Key' => 'shipment-key-p'])->assertCreated()->assertJsonPath('data.fulfilment', 'pickup')->json('data.id');
    staffOrderRequest('POST', "/shipments/{$pickup}/ship")->assertConflict();
    staffOrderRequest('POST', "/shipments/{$pickup}/ready")->assertOk()->assertJsonPath('data.status', 'ready_for_pickup');
    expect($this->store->run(static fn (): array => StockItem::query()->sole()->only(['on_hand', 'reserved'])))->toBe(['on_hand' => 5, 'reserved' => 1]);

    staffOrderRequest('POST', "/shipments/{$pickup}/deliver")->assertOk()->assertJsonPath('data.status', 'delivered');
    expect($this->store->run(static fn (): array => StockItem::query()->sole()->only(['on_hand', 'reserved'])))->toBe(['on_hand' => 4, 'reserved' => 0])
        ->and(staffOrderRequest('GET', "/orders/{$order}")->json('data.status'))->toBe('completed');
});

it('refuses to ship what isn\'t on hand, such as a backorder that hasn\'t arrived', function (): void {
    $shipment = $this->store->run(function (): string {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 1, backorder: true), 3]], orderShippingMethod())))->load('lines');

        return app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 3], AccountReference::to($this->owner), 'TRK')->public_id;
    });

    staffOrderRequest('POST', "/shipments/{$shipment}/ship")->assertConflict()->assertJsonPath('code', 'insufficient_stock');
    expect(staffOrderRequest('GET', "/shipments/{$shipment}")->json('data.status'))->toBe('pending');
});

it('stops a paid order being cancelled once anything has shipped, and cancels its shipments that haven\'t left', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        $method = orderShippingMethod();
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], $method)))->load('lines');
        $pending = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 1], $staff, 'T1');

        $other = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], $method)))->load('lines');
        app(MoveShipment::class)->ship(app(CreateShipment::class)->handle($other, [$other->lines[0]->public_id => 1], $staff, 'T2'), $staff);

        expect(app(CancelOrder::class)->handle($order, CancellationReason::Staff, $staff)->status->value)->toBe('cancelled')
            ->and($pending->refresh()->status->value)->toBe('cancelled')
            ->and(fn () => app(CancelOrder::class)->handle($other, CancellationReason::Staff, $staff))->toThrow(OrderCannotBeCancelledException::class);
    });
});

it('only ships an address correction can still reach: none once a shipment exists', function (): void {
    [$order, $line] = $this->store->run(static function (): array {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], orderShippingMethod())))->load('lines');

        return [$order->public_id, $order->lines[0]->public_id];
    });

    staffOrderRequest('POST', "/orders/{$order}/shipments", ['lines' => [['line' => $line, 'quantity' => 1]]], headers: ['Idempotency-Key' => 'shipment-key-x'])->assertCreated();
    staffOrderRequest('PUT', "/orders/{$order}/shipping-address", orderAddressPayload(['line1' => '9 Awolowo Road']))->assertConflict()->assertJsonPath('code', 'order_address_cannot_be_corrected');
});

it('makes two shipments at the same moment happen one after the other, so a line never ships more than was bought', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], orderShippingMethod())))->load('lines');
        $line = $order->lines[0];

        // Another request holds the order and has added a shipment of both, not yet committed.
        $other = SecondConnection::open()->holdRowLock($order);
        $other->run(function () use ($order, $line): void {
            $connection = DB::connection(SecondConnection::NAME);
            $shipment = $connection->table('shipments')->insertGetId(['public_id' => strtolower((string) Illuminate\Support\Str::ulid()), 'order_id' => $order->id, 'fulfilment' => 'ship', 'stock_location_id' => App\Tenant\Inventory\Models\StockLocation::query()->sole()->id, 'status' => 'pending', 'created_by_type' => 'staff_member', 'created_by_id' => $this->owner->public_id, 'created_at' => now(), 'updated_at' => now()]);
            $connection->table('shipment_lines')->insert(['shipment_id' => $shipment, 'order_line_id' => $line->id, 'quantity' => 2]);
        });

        $ship = static fn () => app(CreateShipment::class)->handle($order, [$line->public_id => 1], $staff);
        expect($other->blockedStatement($ship))->toContain('for update');
        $other->commit();

        expect($ship)->toThrow(ShipmentQuantityTooHighException::class)
            ->and(Shipment::query()->count())->toBe(1);
    });
});

it('makes shipping and cancelling the order at the same moment happen one after the other', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod())))->load('lines');
        $shipment = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 1], $staff, 'TRK');

        // Staff are cancelling the order: they hold it and have cancelled it and its shipment, not yet committed.
        $other = SecondConnection::open()->holdRowLock($order);
        $other->run(static function () use ($order, $shipment): void {
            DB::connection(SecondConnection::NAME)->table('orders')->where('id', $order->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'staff', 'refund_due' => true]);
            DB::connection(SecondConnection::NAME)->table('shipments')->where('id', $shipment->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });

        expect($other->blockedStatement(static fn () => app(MoveShipment::class)->ship($shipment, $staff)))->toContain('for update');
        $other->commit();

        expect(static fn () => app(MoveShipment::class)->ship($shipment, $staff))->toThrow(App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException::class)
            ->and(StockItem::query()->sole()->on_hand)->toBe(5);
    });
});

it('keeps what a shipment holds as it was made, and the database refuses a pickup marked shipped', function (): void {
    $this->store->run(function (): void {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(ShippingFulfilment::Pickup, 'Collect'), 0)))->load('lines');
        $shipment = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 1], AccountReference::to($this->owner));

        expect(fn () => DB::table('shipment_lines')->update(['quantity' => 5]))->toThrow(QueryException::class, 'financial records')
            ->and(fn () => DB::table('shipment_lines')->delete())->toThrow(QueryException::class, 'financial records')
            ->and(fn () => DB::table('shipments')->where('id', $shipment->id)->update(['status' => 'shipped', 'shipped_at' => now()]))->toThrow(QueryException::class, 'shipments_pickups_never_ship')
            ->and(fn () => DB::table('shipments')->where('id', $shipment->id)->update(['tracking_url' => 'http://x.example.com']))->toThrow(QueryException::class, 'shipments_tracking_url_https');
    });
});

it('lets staff with orders.view see shipments, and only orders.fulfil change them', function (): void {
    [$order, $line] = $this->store->run(static function (): array {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], orderShippingMethod())))->load('lines');

        return [$order->public_id, $order->lines[0]->public_id];
    });
    $shipment = staffOrderRequest('POST', "/orders/{$order}/shipments", ['lines' => [['line' => $line, 'quantity' => 1]]], headers: ['Idempotency-Key' => 'shipment-key-v'])->json('data.id');

    staffOrderRequest('GET', '/shipments', as: $this->viewer)->assertOk()->assertJsonPath('data.0.id', $shipment);
    staffOrderRequest('GET', "/orders/{$order}/shipments", as: $this->viewer)->assertOk()->assertJsonPath('data.0.id', $shipment);
    staffOrderRequest('GET', "/shipments/{$shipment}", as: $this->viewer)->assertOk();
    staffOrderRequest('GET', '/shipments?status=shipped')->assertOk()->assertJsonPath('data', []);

    foreach ([['POST', "/orders/{$order}/shipments", ['lines' => [['line' => $line, 'quantity' => 1]]]], ['PATCH', "/shipments/{$shipment}", ['tracking_number' => 'X', 'tracking_url' => null]], ['POST', "/shipments/{$shipment}/ship", []], ['POST', "/shipments/{$shipment}/cancel", []]] as [$method, $path, $data]) {
        staffOrderRequest($method, $path, $data, as: $this->viewer, headers: ['Idempotency-Key' => 'shipment-key-viewer'])->assertForbidden();
    }

    $nobody = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());
    staffOrderRequest('GET', '/shipments', as: $nobody)->assertForbidden();
});
