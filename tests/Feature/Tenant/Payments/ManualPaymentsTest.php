<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Money;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Exceptions\InvalidOrderDraftException;
use App\Tenant\Orders\Exceptions\OrderCannotBeFulfilledException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\FindManualPaymentMethods;
use App\Tenant\Payments\Actions\RecordManualPayment;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Events\CashCollectionOverdue;
use App\Tenant\Payments\Events\PaymentOverpaid;
use App\Tenant\Payments\Exceptions\PaymentMethodNotAvailableException;
use App\Tenant\Payments\Exceptions\PaymentNotAwaitingReceiptException;
use App\Tenant\Payments\Exceptions\PaymentReceiptInvalidException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15.4: payment methods paid by hand. Each store turns bank transfer
 * and cash on delivery on and sets the buyer's deadline within the
 * platform's limits. Staff record what arrived: less opens a payment for
 * the rest by the same deadline, more pays the order and is owed back. An
 * order paid on delivery may ship before it is paid, stops expiring once a
 * shipment exists, and staff are alerted when its cash isn't recorded a
 * while after delivery.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    Queue::fake([CheckPaymentWithGateway::class]);
    $this->freezeTime();
});

afterEach(function (): void {
    deleteAllStores();
});

it('lists the methods paid by hand, off with the platform\'s defaults until the store sets them up', function (): void {
    staffOrderRequest('GET', '/payment-methods')->assertOk()->assertExactJson(['data' => [
        ['method' => 'bank_transfer', 'enabled' => false, 'deadline_hours' => 72, 'collection_alert_hours' => null, 'instructions' => null],
        ['method' => 'cash_on_delivery', 'enabled' => false, 'deadline_hours' => 336, 'collection_alert_hours' => 48, 'instructions' => null],
    ]]);
});

it('lets staff turn a method on and give buyers a deadline within the platform\'s limits', function (): void {
    staffOrderRequest('PATCH', '/payment-methods/bank_transfer', ['enabled' => true, 'deadline_hours' => 48, 'instructions' => 'Pay into GTBank 0123456789'])
        ->assertOk()
        ->assertExactJson(['data' => ['method' => 'bank_transfer', 'enabled' => true, 'deadline_hours' => 48, 'collection_alert_hours' => null, 'instructions' => 'Pay into GTBank 0123456789']]);
    staffOrderRequest('PATCH', '/payment-methods/cash_on_delivery', ['deadline_hours' => 720, 'collection_alert_hours' => 24])
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.collection_alert_hours', 24);

    foreach ([['bank_transfer', ['deadline_hours' => 23]], ['bank_transfer', ['deadline_hours' => 169]], ['bank_transfer', ['collection_alert_hours' => 10]], ['cash_on_delivery', ['deadline_hours' => 71]], ['cash_on_delivery', ['deadline_hours' => 721]], ['cash_on_delivery', ['collection_alert_hours' => 0]]] as [$method, $data]) {
        staffOrderRequest('PATCH', "/payment-methods/{$method}", $data)->assertUnprocessable();
    }

    staffOrderRequest('PATCH', '/payment-methods/gateway', ['enabled' => true])->assertNotFound();
    staffOrderRequest('GET', '/payment-methods', as: $this->viewer)->assertForbidden();
    staffOrderRequest('PATCH', '/payment-methods/bank_transfer', ['enabled' => false], as: $this->viewer)->assertForbidden();

    expect($this->store->run(static fn (): int => app(FindManualPaymentMethods::class)->find(PaymentMethod::BankTransfer)->paymentDueAt()->getTimestamp()))->toBe(now()->addHours(48)->getTimestamp())
        ->and($this->store->run(static fn (): int => unpaidOrder(paymentDueAt: CarbonImmutable::now()->addHours(720))->payment_due_at->getTimestamp()))->toBe(now()->addHours(720)->getTimestamp());
});

it('expects a payment by hand only by a method the store offers, for an order placed for it, and never for a test order', function (): void {
    $start = static fn (Order $order, PaymentMethod $method): Payment => app(StartManualPayment::class)->handle($order, $method);
    expect(fn () => $this->store->run(static fn (): Payment => $start(unpaidOrder(paymentDueAt: CarbonImmutable::now()->addHours(72)), PaymentMethod::BankTransfer)))->toThrow(PaymentMethodNotAvailableException::class);

    offerManualMethod(PaymentMethod::BankTransfer);
    offerManualMethod(PaymentMethod::CashOnDelivery);

    $this->store->run(static function () use ($start): void {
        $order = unpaidOrder(paymentDueAt: CarbonImmutable::now()->addHours(72));
        $onDelivery = unpaidOrder(payOnDelivery: true, paymentDueAt: CarbonImmutable::now()->addHours(72));

        expect(static fn () => $start($order, PaymentMethod::CashOnDelivery))->toThrow(PaymentMethodNotAvailableException::class)
            ->and(static fn () => $start($onDelivery, PaymentMethod::BankTransfer))->toThrow(PaymentMethodNotAvailableException::class)
            ->and(static fn () => $start(unpaidOrder(isTest: true), PaymentMethod::BankTransfer))->toThrow(PaymentMethodNotAvailableException::class)
            ->and(static fn () => $start($order, PaymentMethod::Gateway))->toThrow(PaymentMethodNotAvailableException::class);

        $payment = $start($order, PaymentMethod::BankTransfer);

        expect($start($order, PaymentMethod::BankTransfer)->id)->toBe($payment->id)
            ->and([$payment->method->value, $payment->status->value, $payment->is_test])->toBe(['bank_transfer', 'pending', false])
            ->and($payment->amount->isEqualTo($order->total))->toBeTrue()
            ->and($start($onDelivery, PaymentMethod::CashOnDelivery)->method->value)->toBe('cash_on_delivery');
    });
});

it('pays the order with what staff record as arrived, saying who recorded it', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);

    $paid = recordReceived($payment, $order->total->minorAmount(), 'GTB-778');

    $this->store->run(function () use ($order, $paid): void {
        expect([$paid->status->value, $paid->received?->minorAmount(), $paid->confirmed_by_type, $paid->confirmed_by_id])->toBe(['succeeded', $order->total->minorAmount(), 'staff_member', AccountReference::to($this->owner)->publicId])
            ->and($order->refresh()->payment_status->value)->toBe('paid')
            ->and($paid->events()->get()->last()?->data)->toMatchArray(['reference' => 'GTB-778']);
    });
});

it('expects the rest by the same deadline when less arrived, and counts what did', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    $total = $order->total->minorAmount();

    recordReceived($payment, 4000);

    $rest = $this->store->run(function () use ($order, $payment, $total): Payment {
        $rest = Payment::query()->where('order_id', $order->id)->whereKeyNot($payment->id)->sole();

        expect($order->refresh()->payment_status->value)->toBe('partially_paid')
            ->and($payment->refresh()->received?->minorAmount())->toBe(4000)
            ->and([$rest->method->value, $rest->status->value, $rest->amount->minorAmount()])->toBe(['bank_transfer', 'pending', $total - 4000]);

        return $rest;
    });

    recordReceived($rest, $total - 4000);

    expect($this->store->run(static fn (): array => [$order->refresh()->payment_status->value, Payment::query()->where('order_id', $order->id)->count()]))->toBe(['paid', 2]);
});

it('cancels an order still short at its deadline, owing back what arrived, and stops expecting the rest', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    recordReceived($payment, 4000);
    $this->travel(73)->hours();

    $this->artisan('inventory:release-expired-reservations')->assertSuccessful();

    $this->store->run(static function () use ($order, $payment): void {
        expect($order->refresh()->only(['status', 'refund_due']))->toMatchArray(['refund_due' => true])
            ->and($order->status->value)->toBe('cancelled')
            ->and(Payment::query()->where('order_id', $order->id)->whereKeyNot($payment->id)->sole()->status->value)->toBe('cancelled');
    });
});

it('counts all of an overpayment, paying the order, and tells staff the difference is owed back', function (): void {
    Event::fake([PaymentOverpaid::class]);
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);

    recordReceived($payment, $order->total->minorAmount() + 500);

    expect($this->store->run(static fn (): array => [$order->refresh()->payment_status->value, paymentHistory($payment)]))->toBe(['paid', ['started', 'succeeded', 'overpaid']]);
    Event::assertDispatched(PaymentOverpaid::class, static fn (PaymentOverpaid $event): bool => $event->payment->is($payment) && $event->excess->minorAmount() === 500);
});

it('refuses to record money that isn\'t a positive amount in the payment\'s currency, or for a payment not waiting for it', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    $record = fn (Payment $payment, Money $amount): Payment => $this->store->run(fn (): Payment => app(RecordManualPayment::class)->handle($this->owner, $payment->refresh(), $amount));

    expect(static fn () => $record($payment, Money::ofMinor(100, 'USD')))->toThrow(PaymentReceiptInvalidException::class)
        ->and(static fn () => $record($payment, Money::zero('NGN')))->toThrow(PaymentReceiptInvalidException::class);

    recordReceived($payment, $order->total->minorAmount());
    expect(static fn () => $record($payment, Money::ofMinor(100, 'NGN')))->toThrow(PaymentNotAwaitingReceiptException::class);

    useFakeGateway();
    connectFakeGateway('live');
    $gatewayPayment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
    expect(static fn () => $record($gatewayPayment, Money::ofMinor(100, 'NGN')))->toThrow(PaymentNotAwaitingReceiptException::class);
});

it('stops expecting money by hand when its order is cancelled, but still counts money that arrives anyway and owes it back', function (): void {
    useFakeGateway();
    connectFakeGateway('live');
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    $gatewayPayment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));

    $this->store->run(function () use ($order, $gatewayPayment): void {
        app(CancelOrder::class)->byStaff($this->owner, $order);
        app(CancelOrder::class)->byStaff($this->owner, $gatewayPayment->order);
    });
    expect($this->store->run(static fn (): array => [$payment->refresh()->status->value, $gatewayPayment->refresh()->status->value]))->toBe(['cancelled', 'pending']);

    // Less than was expected: it counts and is owed back, and nothing more is expected of a cancelled order.
    recordReceived($payment, 4000);

    expect($this->store->run(static fn (): array => [$payment->refresh()->status->value, $order->refresh()->status->value, $order->refund_due]))->toBe(['succeeded', 'cancelled', true])
        ->and($this->store->run(static fn (): int => Payment::query()->where('order_id', $order->id)->count()))->toBe(1);
});

it('lets an order paid on delivery ship before it is paid, and completes it only once the cash is recorded', function (): void {
    [$onDelivery, $cash] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    [$byTransfer] = manuallyPaidOrder(PaymentMethod::BankTransfer);

    $this->store->run(function () use ($onDelivery, $byTransfer): void {
        expect(static fn () => shipOrder($byTransfer))->toThrow(OrderCannotBeFulfilledException::class);
        shipOrder($onDelivery, deliver: true);

        $onDelivery->refresh();
        expect([$onDelivery->status->value, $onDelivery->fulfilment_status->value, $onDelivery->payment_status->value])->toBe(['open', 'fulfilled', 'unpaid']);
    });

    recordReceived($cash, $onDelivery->total->minorAmount());

    expect($this->store->run(static fn (): string => $onDelivery->refresh()->status->value))->toBe('completed');
});

it('stops an order paid on delivery from expiring once a shipment exists, and lets it expire again if that shipment is cancelled', function (): void {
    [$onItsWay] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    [$notSent] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    [$recalled] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    $this->store->run(function () use ($onItsWay, $recalled): void {
        shipOrder($onItsWay);
        $staff = AccountReference::to($this->owner);
        app(MoveShipment::class)->cancel(app(CreateShipment::class)->handle($recalled, [$recalled->lines()->firstOrFail()->public_id => 1], $staff), $staff);
    });
    $this->travel(73)->hours();
    expect($this->store->run(static fn (): bool => app(CancelOrder::class)->expire($onItsWay)))->toBeFalse();

    // One at a time, oldest deadline first: the order on its way, the oldest, never holds up the others.
    config(['orders.expired_orders_per_run' => 1]);
    $this->artisan('inventory:release-expired-reservations')->assertSuccessful();
    $this->artisan('inventory:release-expired-reservations')->assertSuccessful();

    expect($this->store->run(static fn (): array => [$onItsWay->refresh()->status->value, $notSent->refresh()->status->value, $recalled->refresh()->status->value]))->toBe(['open', 'cancelled', 'cancelled']);
});

it('alerts staff once when cash on delivery isn\'t recorded a while after delivery, after the store\'s own wait', function (): void {
    Event::fake([CashCollectionOverdue::class]);
    [$late, $cash] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    [$collected, $collectedCash] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    $this->store->run(static function () use ($late, $collected): void {
        shipOrder($late, deliver: true);
        shipOrder($collected, deliver: true);
    });
    recordReceived($collectedCash, $collected->total->minorAmount());

    $this->travel(47)->hours();
    $this->artisan('payments:check-pending')->assertSuccessful();
    Event::assertNotDispatched(CashCollectionOverdue::class);

    $this->travel(2)->hours();
    $this->artisan('payments:check-pending')->assertSuccessful();
    $this->artisan('payments:check-pending')->assertSuccessful();

    Event::assertDispatchedTimes(CashCollectionOverdue::class, 1);
    Event::assertDispatched(CashCollectionOverdue::class, static fn (CashCollectionOverdue $event): bool => $event->payment->is($cash));
    expect($this->store->run(static fn (): array => paymentHistory($cash)))->toBe(['started', 'collection_overdue']);

    // A store that waits less is told sooner.
    offerManualMethod(PaymentMethod::CashOnDelivery, ['collection_alert_hours' => 24]);
    [$sooner, $soonerCash] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    $this->store->run(static fn () => shipOrder($sooner, deliver: true));
    $this->travel(25)->hours();
    $this->artisan('payments:check-pending')->assertSuccessful();
    Event::assertDispatched(CashCollectionOverdue::class, static fn (CashCollectionOverdue $event): bool => $event->payment->is($soonerCash));
});

it('records money by hand under the order\'s lock, so it waits for anything changing the order', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);

    $this->store->run(function () use ($order, $payment): void {
        $other = SecondConnection::open()->holdRowLock($order);
        DB::enableQueryLog();
        expect($other->blockedStatement(fn () => app(RecordManualPayment::class)->handle($this->owner, $payment, $order->total)))->toContain('"orders"')->toContain('for update')
            // It waits for the order before touching the payment, so the order's lock always comes first.
            ->and(collect(DB::getQueryLog())->pluck('query')->filter(static fn (string $query): bool => str_contains($query, '"payments"'))->all())->toBe([]);
        DB::disableQueryLog();
        $other->rollBack();
    });
});

it('keeps how an order is paid for as it was placed, and never pays a test order on delivery', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    [, $transfer] = manuallyPaidOrder(PaymentMethod::BankTransfer);

    $this->store->run(static function () use ($order, $payment, $transfer): void {
        expect(static fn () => DB::table('orders')->where('id', $order->id)->update(['pay_on_delivery' => false]))->toThrow(QueryException::class, 'way of paying never change')
            ->and(static fn () => placeOrder(orderDraft([[cartVariant(5000, stock: 5), 1]], orderShippingMethod(), isTest: true, payOnDelivery: true)))->toThrow(InvalidOrderDraftException::class)
            ->and(static fn () => rawOrder(['is_test' => true, 'pay_on_delivery' => true]))->toThrow(QueryException::class, 'orders_pay_on_delivery_never_test')
            ->and(static fn () => DB::table('payments')->where('id', $transfer->id)->update(['collection_alerted_at' => now()]))->toThrow(QueryException::class, 'payments_collection_alert_only_on_delivery')
            ->and(static fn () => DB::table('payment_method_settings')->where('method', 'bank_transfer')->update(['collection_alert_hours' => 5]))->toThrow(QueryException::class, 'payment_method_settings_alert_only_on_delivery')
            ->and(DB::table('payments')->where('id', $payment->id)->update(['collection_alerted_at' => now()]))->toBe(1);
    });
});
