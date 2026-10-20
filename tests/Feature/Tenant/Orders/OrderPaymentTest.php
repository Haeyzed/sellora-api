<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Decimal;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Events\OrderCancelled;
use App\Tenant\Orders\Events\OrderCompleted;
use App\Tenant\Orders\Events\OrderPaid;
use App\Tenant\Orders\Events\OrderPaidAfterCancellation;
use App\Tenant\Orders\Exceptions\OrderAlreadyPaidException;
use App\Tenant\Orders\Exceptions\OrderCannotBeCancelledException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 14c: payment and cancellation. Payments (Step 15) marks an order paid:
 * its promotion uses are committed, lines that don't ship are fulfilled with
 * their stock leaving the shelf, and it completes once nothing is left to
 * send. Staff cancel an open order before anything ships (a paid one then
 * owes a refund); a customer only before paying; the sweep cancels unpaid
 * orders past their deadline. Cancelling gives back stock and promotion uses.
 * A payment for a cancelled order never revives it. Every change locks the
 * order, so two at once happen one after the other.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('marks an open order paid, commits its promotion uses, and fulfils and completes an order that doesn\'t ship', function (): void {
    Event::fake([OrderPaid::class, OrderCompleted::class]);

    [$order, $uses, $stock] = $this->store->run(static function (): array {
        runningPromotion(['percent' => Decimal::of(10)]);
        $service = cartVariant(10000, stock: 5);
        $service->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();
        $order = markPaid(placeOrder(orderDraft([[$service, 2]])));

        return [$order->refresh()->load('events'), PromotionRedemption::query()->sole(), StockItem::query()->sole()];
    });

    expect([$order->status->value, $order->payment_status->value, $order->fulfilment_status->value])->toBe(['completed', 'paid', 'not_required'])
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->completed_at)->not->toBeNull()
        ->and($order->events->map(static fn ($event): string => $event->type->value)->all())->toBe(['placed', 'paid', 'completed'])
        ->and([$uses->status->value, $uses->holder_type, $uses->expires_at])->toBe(['committed', 'order', null])
        // Two left the shelf when paid for.
        ->and([$stock->on_hand, $stock->reserved])->toBe([3, 0]);

    Event::assertDispatchedTimes(OrderPaid::class, 1);
    Event::assertDispatchedTimes(OrderCompleted::class, 1);
});

it('keeps an order that ships open once paid, with its stock still held until it ships, and commits only the lines that don\'t ship', function (): void {
    [$order, $levels] = $this->store->run(static function (): array {
        $shirt = cartVariant(10000, stock: 5, name: 'Shirt');
        $giftWrap = cartVariant(500, stock: 10, name: 'Gift wrap');
        $giftWrap->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();
        $order = markPaid(placeOrder(orderDraft([[$shirt, 2], [$giftWrap, 1]], orderShippingMethod())));

        return [$order->refresh(), [StockItem::query()->where('product_variant_id', $shirt->id)->sole()->only(['on_hand', 'reserved']), StockItem::query()->where('product_variant_id', $giftWrap->id)->sole()->only(['on_hand', 'reserved'])]];
    });

    expect([$order->status->value, $order->payment_status->value, $order->fulfilment_status->value])->toBe(['open', 'paid', 'unfulfilled'])
        ->and($levels)->toBe([['on_hand' => 5, 'reserved' => 2], ['on_hand' => 9, 'reserved' => 0]]);
});

it('records a payment once, however often it is reported', function (): void {
    $events = $this->store->run(static function (): array {
        $order = placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()));
        markPaid($order);
        markPaid($order);

        return $order->events()->pluck('type')->map->value->all();
    });

    // Paid in full once: one invoice too.
    expect($events)->toBe(['placed', 'paid', 'invoice_issued']);
});

it('never revives a cancelled order a payment arrives for: it stays cancelled, owes a refund, and staff are alerted', function (): void {
    Event::fake([OrderPaidAfterCancellation::class]);
    Log::spy();

    [$order, $uses, $stock] = $this->store->run(static function (): array {
        runningPromotion(['percent' => Decimal::of(10)]);
        $order = placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], orderShippingMethod()));

        // Not due yet: nothing happens.
        expect(app(CancelOrder::class)->expire($order))->toBeFalse();

        test()->travel(61)->minutes();
        expect(app(CancelOrder::class)->expire($order))->toBeTrue();
        markPaid($order);

        return [$order->refresh()->load('events'), PromotionRedemption::query()->sole(), StockItem::query()->sole()];
    });

    expect([$order->status->value, $order->payment_status->value, $order->refund_due, $order->cancellation_reason?->value])->toBe(['cancelled', 'paid', true, 'payment_expired'])
        ->and($order->events->map(static fn ($event): string => $event->type->value)->all())->toBe(['placed', 'cancelled', 'paid_after_cancellation'])
        ->and($uses->status->value)->toBe('released')
        ->and($stock->reserved)->toBe(0);

    Event::assertDispatched(OrderPaidAfterCancellation::class);
    Log::shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'cancelled order') && $context === ['order' => $order->public_id])->once();
});

it('lets staff cancel an open order before it ships, giving back its stock and promotion uses; a paid one then owes a refund', function (): void {
    Event::fake([OrderCancelled::class]);

    [$unpaid, $paid, $stock, $uses] = $this->store->run(function (): array {
        runningPromotion(['percent' => Decimal::of(10)]);
        $shirt = cartVariant(10000, stock: 5);
        $method = orderShippingMethod();
        $staff = new AccountReference('staff_member', 'staff-1');

        $unpaid = app(CancelOrder::class)->handle(placeOrder(orderDraft([[$shirt, 2]], $method)), CancellationReason::Staff, $staff);
        $paid = app(CancelOrder::class)->handle(markPaid(placeOrder(orderDraft([[$shirt, 1]], $method))), CancellationReason::Staff, $staff);

        return [$unpaid->refresh()->load('events'), $paid->refresh(), StockItem::query()->sole(), PromotionRedemption::query()->pluck('status')->map->value->all()];
    });

    expect([$unpaid->status->value, $unpaid->refund_due, $unpaid->cancellation_reason?->value, $unpaid->cancelled_by_type, $unpaid->cancelled_by_id])->toBe(['cancelled', false, 'staff', 'staff_member', 'staff-1'])
        ->and($unpaid->events->last()?->only(['actor_type', 'actor_id', 'data']))->toEqual(['actor_type' => 'staff_member', 'actor_id' => 'staff-1', 'data' => ['from' => 'open', 'to' => 'cancelled', 'reason' => 'staff', 'refund_due' => false]])
        ->and([$paid->status->value, $paid->payment_status->value, $paid->refund_due])->toBe(['cancelled', 'paid', true])
        ->and([$stock->on_hand, $stock->reserved])->toBe([5, 0])
        ->and($uses)->toBe(['released', 'released']);

    Event::assertDispatchedTimes(OrderCancelled::class, 2);
});

it('lets a customer cancel their order only before paying', function (): void {
    $this->store->run(static function (): void {
        $customer = Customer::factory()->create();
        $method = orderShippingMethod();
        $them = AccountReference::to($customer);
        $unpaid = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        $paid = markPaid(placeOrder(orderDraft([[cartVariant(10000), 1]], $method)));

        expect(app(CancelOrder::class)->handle($unpaid, CancellationReason::Customer, $them)->status->value)->toBe('cancelled')
            ->and(fn () => app(CancelOrder::class)->handle($paid, CancellationReason::Customer, $them))->toThrow(OrderAlreadyPaidException::class)
            ->and($paid->refresh()->status->value)->toBe('open');
    });
});

it('refuses to cancel an order that is completed, cancelled, or has started to ship', function (array $columns): void {
    $this->store->run(static function () use ($columns): void {
        $order = placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()));
        DB::table('orders')->where('id', $order->id)->update($columns);

        expect(fn () => app(CancelOrder::class)->handle($order, CancellationReason::Staff, new AccountReference('staff_member', 'staff-1')))->toThrow(OrderCannotBeCancelledException::class);
    });
})->with([
    'completed' => [['status' => 'completed', 'completed_at' => now(), 'payment_status' => 'paid', 'paid_at' => now(), 'fulfilment_status' => 'fulfilled']],
    'cancelled' => [['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'customer']],
    'partly shipped' => [['fulfilment_status' => 'partially_fulfilled']],
]);

it('cancels unpaid orders once their payment deadline passes, in the scheduled sweep, and nothing else', function (): void {
    $other = createStore('other-store', ['country_code' => 'NG']);
    [$due, $notYet, $paid] = $this->store->run(static function (): array {
        $method = orderShippingMethod();
        $shirt = cartVariant(10000, stock: 5);

        return [
            placeOrder(orderDraft([[$shirt, 1]], $method)),
            placeOrder(orderDraft([[$shirt, 1]], $method, paymentDueAt: now()->addDays(2)->toImmutable())),
            markPaid(placeOrder(orderDraft([[$shirt, 1]], $method))),
        ];
    });
    $theirs = $other->run(static fn (): Order => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod())));

    $this->travel(61)->minutes();
    expect(Artisan::call('inventory:release-expired-reservations'))->toBe(0);

    $statuses = $this->store->run(static fn (): array => Order::query()->orderBy('id')->get()->map(static fn (Order $order): array => [$order->status->value, $order->cancellation_reason?->value])->all());

    expect($statuses)->toBe([['cancelled', 'payment_expired'], ['open', null], ['open', null]])
        ->and($other->run(static fn (): string => Order::query()->sole()->status->value))->toBe('cancelled')
        ->and($this->store->run(static fn (): int => StockItem::query()->sole()->reserved))->toBe(2)
        ->and([$due->number, $notYet->number, $paid->number, $theirs->number])->toBe([1001, 1002, 1003, 1001]);
});

it('cancels expired orders a batch at a time', function (): void {
    config()->set('orders.expired_orders_per_run', 2);

    $this->store->run(static function (): void {
        $method = orderShippingMethod();

        foreach (range(1, 3) as $ignored) {
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        }
    });

    $this->travel(61)->minutes();
    Artisan::call('inventory:release-expired-reservations');

    expect($this->store->run(static fn (): array => Order::query()->orderBy('id')->pluck('status')->map->value->all()))->toBe(['cancelled', 'cancelled', 'open']);
});

it('makes a payment and a cancellation at the same moment happen one after the other: the payment then owes a refund', function (): void {
    $this->store->run(static function (): void {
        $order = placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod()));

        // Staff are cancelling it: they hold its row and have cancelled it, but not yet committed.
        $staff = SecondConnection::open()->holdRowLock($order);
        $staff->run(static fn () => DB::connection(SecondConnection::NAME)->table('orders')->where('id', $order->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'staff']));

        expect($staff->blockedStatement(static fn () => markPaid($order)))->toContain('for update');
        $staff->commit();

        markPaid($order);
        expect($order->refresh()->only(['refund_due']))->toBe(['refund_due' => true])
            ->and([$order->status->value, $order->payment_status->value])->toBe(['cancelled', 'paid']);
    });
});

it('never lets the sweep cancel an order a payment arrives for at the same moment', function (): void {
    $this->store->run(function (): void {
        $order = placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod()));
        $this->travel(61)->minutes();

        // A payment is being recorded: it holds the row and has marked it paid, but not yet committed.
        $payment = SecondConnection::open()->holdRowLock($order);
        $payment->run(static fn () => DB::connection(SecondConnection::NAME)->table('orders')->where('id', $order->id)->update(['payment_status' => 'paid', 'paid_at' => now()]));

        expect($payment->blockedStatement(static fn () => app(CancelOrder::class)->expire($order)))->toContain('for update');
        $payment->commit();

        expect(app(CancelOrder::class)->expire($order))->toBeFalse()
            ->and($order->refresh()->status->value)->toBe('open')
            ->and(StockReservation::query()->sole()->status->value)->toBe('active');
    });
});

it('makes two cancellations at the same moment happen one after the other: the second is refused', function (): void {
    $this->store->run(static function (): void {
        $order = placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod()));
        $cancel = static fn () => app(CancelOrder::class)->handle($order, CancellationReason::Staff, new AccountReference('staff_member', 'staff-1'));

        $other = SecondConnection::open()->holdRowLock($order);
        $other->run(static fn () => DB::connection(SecondConnection::NAME)->table('orders')->where('id', $order->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'customer']));

        expect($other->blockedStatement($cancel))->toContain('for update');
        $other->commit();

        expect($cancel)->toThrow(OrderCannotBeCancelledException::class)
            ->and($order->refresh()->cancellation_reason?->value)->toBe('customer');
    });
});
