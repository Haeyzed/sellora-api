<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Actions\ReturnCommittedStock;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Orders\Actions\CancelExpiredOrders;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Events\ShipmentReturned;
use App\Tenant\Orders\Exceptions\OrderCannotBeCancelledException;
use App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 17: one of the store's own deliveries that comes back undelivered is
 * returned. What it held goes back on the shelf, held for the order again, so
 * it can ship once more or be given back when the order is cancelled. An own
 * delivery counts as fulfilled only once delivered, and an order can't be
 * cancelled while a delivery is with a driver. An unpaid order's payment
 * deadline runs again from the return.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner] = $this->store->run(static fn (): array => orderStaff());
});

afterEach(function (): void {
    deleteAllStores();
});

it('puts a returned delivery\'s stock back on the shelf, held for the order, so it can ship again', function (): void {
    Event::fake([ShipmentReturned::class]);
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        [$order, $shipment] = shippedOwnDelivery($staff);

        // With the driver: off the shelf, but not fulfilled until delivered.
        expect(StockItem::query()->sole()->only(['on_hand', 'reserved']))->toBe(['on_hand' => 3, 'reserved' => 0])
            ->and($order->refresh()->fulfilment_status->value)->toBe('unfulfilled');

        $returned = app(MoveShipment::class)->returnToStore($shipment, $staff);

        expect($returned->status->value)->toBe('returned')
            ->and($returned->returned_at)->not->toBeNull()
            ->and(StockItem::query()->sole()->only(['on_hand', 'reserved']))->toBe(['on_hand' => 5, 'reserved' => 2])
            ->and(StockReservation::query()->where('holder_type', 'order')->where('status', 'active')->sole()->quantity)->toBe(2)
            ->and(StockMovement::query()->latest('id')->first()?->type->value)->toBe('returned')
            ->and($order->refresh()->status->value)->toBe('open')
            ->and($order->events()->pluck('type')->map->value->all())->toContain('shipment_returned');

        // Ship it again, and this time it arrives.
        $again = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 2], $staff);
        app(MoveShipment::class)->deliver(app(MoveShipment::class)->ship($again, $staff), $staff);

        expect(StockItem::query()->sole()->only(['on_hand', 'reserved']))->toBe(['on_hand' => 3, 'reserved' => 0])
            ->and($order->refresh()->only(['fulfilment_status', 'status']))->toMatchArray(['fulfilment_status' => App\Tenant\Orders\Enums\FulfilmentStatus::Fulfilled, 'status' => App\Tenant\Orders\Enums\OrderStatus::Completed]);
    });
    Event::assertDispatchedTimes(ShipmentReturned::class, 1);

    $shown = staffOrderRequest('GET', '/shipments?status=returned')->assertOk()->json('data.0');
    expect($shown['status'])->toBe('returned')->and($shown['returned_at'])->not->toBeNull();
});

it('returns only an own delivery that has shipped, and only once', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        $carrier = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod())))->load('lines');
        $byCarrier = app(MoveShipment::class)->ship(app(CreateShipment::class)->handle($carrier, [$carrier->lines[0]->public_id => 1], $staff, 'TRK'), $staff);
        $own = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(ShippingFulfilment::OwnDelivery, 'Same day'))))->load('lines');
        $pending = app(CreateShipment::class)->handle($own, [$own->lines[0]->public_id => 1], $staff);

        expect(static fn () => app(MoveShipment::class)->returnToStore($byCarrier, $staff))->toThrow(ShipmentTransitionNotAllowedException::class)
            ->and(static fn () => app(MoveShipment::class)->returnToStore($pending, $staff))->toThrow(ShipmentTransitionNotAllowedException::class);

        $returned = app(MoveShipment::class)->returnToStore(app(MoveShipment::class)->ship($pending, $staff), $staff);
        $stock = StockItem::query()->where('product_variant_id', $own->lines[0]->product_variant_id)->sole()->only(['on_hand', 'reserved']);

        expect(static fn () => app(MoveShipment::class)->returnToStore($returned, $staff))->toThrow(ShipmentTransitionNotAllowedException::class)
            ->and(static fn () => app(MoveShipment::class)->deliver($returned, $staff))->toThrow(ShipmentTransitionNotAllowedException::class)
            ->and(StockItem::query()->where('product_variant_id', $own->lines[0]->product_variant_id)->sole()->only(['on_hand', 'reserved']))->toBe($stock)
            // The database refuses a carrier shipment marked returned, or a returned one without the time.
            ->and(static fn () => DB::table('shipments')->where('id', $byCarrier->id)->update(['status' => 'returned', 'returned_at' => now()]))->toThrow(QueryException::class, 'shipments_returned_only_own_delivery')
            ->and(static fn () => DB::table('shipments')->where('id', $returned->id)->update(['returned_at' => null]))->toThrow(QueryException::class, 'shipments_returned_at');
    });
});

it('stops an order being cancelled while its delivery is with a driver, and gives its stock back once it has come back', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        [$order, $shipment] = shippedOwnDelivery($staff);

        expect(static fn () => app(CancelOrder::class)->handle($order, CancellationReason::Staff, $staff))->toThrow(OrderCannotBeCancelledException::class);

        app(MoveShipment::class)->returnToStore($shipment, $staff);
        app(CancelOrder::class)->handle($order, CancellationReason::Staff, $staff);

        expect($order->refresh()->status->value)->toBe('cancelled')
            ->and(StockItem::query()->sole()->only(['on_hand', 'reserved']))->toBe(['on_hand' => 5, 'reserved' => 0]);
    });
});

it('lets an unpaid order paid on delivery expire again once its delivery comes back, with its deadline restarted from the return', function (): void {
    [$order, $shipment, $later] = $this->store->run(fn (): array => [
        ...shippedOwnDelivery(AccountReference::to($this->owner), payOnDelivery: true),
        unpaidOrder(paymentDueAt: CarbonImmutable::now()->addHours(150)),
    ]);
    // One at a time, oldest deadline first: an order whose deadline restarted must not take the place of one that has expired.
    $expire = fn (): int => $this->store->run(static fn (): int => app(CancelExpiredOrders::class)->handle(1));
    $status = fn (Order $of): string => $this->store->run(static fn (): string => $of->refresh()->status->value);

    // Past its deadline while with the driver: it doesn't expire.
    $this->travel(100)->hours();
    expect($expire())->toBe(0);

    $this->store->run(fn (): Shipment => app(MoveShipment::class)->returnToStore($shipment, AccountReference::to($this->owner)));

    // Its 72 hours start again from the return, while the other order's deadline passes.
    $this->travel(71)->hours();
    expect($expire())->toBe(1)
        ->and($status($later))->toBe('cancelled')
        ->and($status($order))->toBe('open')
        ->and($this->store->run(static fn (): bool => app(CancelOrder::class)->expire($order)))->toBeFalse();

    $this->travel(2)->hours();
    expect($expire())->toBe(1)
        ->and($this->store->run(static fn (): string => $order->refresh()->cancellation_reason?->value ?? ''))->toBe('payment_expired');
});

it('never puts back more than left the shelf, even when two returns happen at once', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        [$order] = shippedOwnDelivery($staff);
        $holder = StockHolder::of($order);
        $variant = $order->lines[0]->product_variant_id;
        $item = StockItem::query()->sole();
        $reservation = StockReservation::query()->where('holder_type', 'order')->sole();

        // Another request holds the stock level and has put both back, not yet committed.
        $other = SecondConnection::open()->holdRowLock($item);
        $other->run(static function () use ($item, $reservation, $variant): void {
            $connection = DB::connection(SecondConnection::NAME);
            $connection->table('stock_items')->where('id', $item->id)->update(['on_hand' => 5, 'reserved' => 2]);
            $connection->table('stock_reservations')->insert(['public_id' => strtolower((string) Illuminate\Support\Str::ulid()), 'product_variant_id' => $variant, 'stock_location_id' => $item->stock_location_id, 'holder_type' => 'order', 'holder_id' => $reservation->holder_id, 'quantity' => 2, 'backordered_quantity' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $connection->table('stock_movements')->insert(['public_id' => strtolower((string) Illuminate\Support\Str::ulid()), 'stock_item_id' => $item->id, 'product_variant_id' => $variant, 'stock_location_id' => $item->stock_location_id, 'type' => 'returned', 'on_hand_delta' => 2, 'reserved_delta' => 2, 'on_hand_after' => 5, 'reserved_after' => 2, 'source_type' => 'stock_reservation', 'source_id' => $reservation->public_id, 'created_at' => now()]);
        });

        $return = static fn (): int => app(ReturnCommittedStock::class)->handle($holder, [$variant => 2], $staff);
        expect($other->blocks($return))->toBeTrue();
        $other->commit();

        expect($return())->toBe(0)
            ->and(StockItem::query()->sole()->only(['on_hand', 'reserved']))->toBe(['on_hand' => 5, 'reserved' => 2]);
    });
});

it('puts back only what left: none of a variant whose stock isn\'t counted, and no more than was sent', function (): void {
    $this->store->run(function (): void {
        $staff = AccountReference::to($this->owner);
        $uncounted = cartVariant(10000);
        $order = markPaid(placeOrder(orderDraft([[$uncounted, 1]], orderShippingMethod(ShippingFulfilment::OwnDelivery, 'Same day'))))->load('lines');
        $shipment = app(MoveShipment::class)->ship(app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 1], $staff), $staff);

        app(MoveShipment::class)->returnToStore($shipment, $staff);
        expect(StockMovement::query()->count())->toBe(0);

        [$counted] = shippedOwnDelivery($staff);
        expect(app(ReturnCommittedStock::class)->handle(StockHolder::of($counted), [$counted->lines[0]->product_variant_id => 9], $staff))->toBe(2)
            ->and(app(ReturnCommittedStock::class)->handle(StockHolder::of($counted), [$counted->lines[0]->product_variant_id => 1], $staff))->toBe(0);
    });
});
