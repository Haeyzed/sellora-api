<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Money;
use App\Tenant\Delivery\Actions\AssignDriver;
use App\Tenant\Delivery\Actions\MoveDelivery;
use App\Tenant\Delivery\Actions\RecordCashHandover;
use App\Tenant\Delivery\Exceptions\CashHandoverExceedsBalanceException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DriverCashEntry;
use App\Tenant\Delivery\Services\DriverCashLedger;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 10, Step 17: the cash each driver holds. Cash a driver collects on
 * delivery pays the order's pending cash payment and goes into their cash
 * ledger in the same transaction; staff with delivery.cash.manage record
 * what they hand over, never more than they hold, checked under the
 * driver's lock. Amounts are per currency and never mixed. The ledger is
 * append-only, never holds test-order cash, is in the driver's personal
 * data export and is kept when the driver is erased.
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

it('pays the order with the cash the driver collected and adds it to what they hold, in one go', function (): void {
    $delivery = cashDelivery($this->store, $this->owner, $this->driver);
    $due = cashToCollect($this->store, $delivery);

    expect(driverAppRequest('GET', "deliveries/{$delivery->public_id}", token: $this->token)->json('data.cash_to_collect.amount'))->toBe($due);

    deliverWithCash($delivery->public_id, $due, $this->token)->assertOk()->assertJsonPath('data.status', 'delivered');

    $this->store->run(function () use ($delivery, $due): void {
        $payment = Payment::query()->where('status', 'succeeded')->sole();
        $entry = DriverCashEntry::query()->sole();

        expect([$payment->confirmed_by_type, $payment->confirmed_by_id])->toBe(['driver', $this->driver->public_id])
            ->and([$entry->type->value, $entry->cash_amount, $entry->delivery_id, $entry->payment_public_id, $entry->recorded_by_id])->toBe(['collected', $due, $delivery->id, $payment->public_id, $this->driver->public_id])
            ->and($delivery->shipment->order->refresh()->status->value)->toBe('completed');
    });

    $currency = $this->store->run(static fn (): string => DriverCashEntry::query()->sole()->cash_currency);
    expect(heldCash(driverAppRequest('GET', 'cash', token: $this->token)->assertOk()))->toBe([['amount' => $due, 'currency' => $currency]]);
    deliveryRequest('GET', "drivers/{$this->driver->public_id}/cash", as: $this->owner)->assertOk()->assertJsonPath('data.balances.0.amount', $due);
    deliveryRequest('GET', "drivers/{$this->driver->public_id}/cash-entries", as: $this->owner)->assertOk()->assertJsonPath('data.0.type', 'collected');
});

it('asks how much cash was collected when there is cash to collect, and changes nothing until it is told', function (): void {
    $delivery = cashDelivery($this->store, $this->owner, $this->driver);

    deliverWithCash($delivery->public_id, null, $this->token)->assertUnprocessable()->assertJsonPath('code', 'cash_collection_required');

    $this->store->run(function () use ($delivery): void {
        expect($delivery->refresh()->status->value)->toBe('picked_up')
            ->and($delivery->shipment->refresh()->status->value)->toBe('shipped')
            ->and(Payment::query()->sole()->status->value)->toBe('pending')
            ->and(DriverCashEntry::query()->count())->toBe(0);
    });
});

it('writes the payment and the ledger entry together, or neither', function (): void {
    $delivery = cashDelivery($this->store, $this->owner, $this->driver);
    $due = cashToCollect($this->store, $delivery);
    driverAppRequest('POST', "deliveries/{$delivery->public_id}/pick-up", token: $this->token)->assertOk();

    $this->store->run(function () use ($delivery, $due): void {
        // Something already holds this delivery's collection in the ledger, so writing it again fails.
        DB::table('driver_cash_entries')->insert([
            'public_id' => strtolower((string) Str::ulid()), 'driver_id' => $this->driver->id, 'type' => 'collected', 'cash_amount' => 1, 'cash_currency' => 'NGN',
            'delivery_id' => $delivery->id, 'payment_public_id' => 'x', 'recorded_by_type' => 'driver', 'recorded_by_id' => $this->driver->public_id, 'created_at' => now(),
        ]);

        expect(fn () => app(MoveDelivery::class)->deliver($delivery, AccountReference::to($this->driver), 'Ada Obi', $due))->toThrow(QueryException::class, 'driver_cash_entries_one_collection_per_delivery')
            ->and(Payment::query()->sole()->status->value)->toBe('pending')
            ->and($delivery->refresh()->status->value)->toBe('picked_up');
    });
});

it('never takes cash for a test order into the ledger', function (): void {
    $delivery = $this->store->run(function (): Delivery {
        $order = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(App\Tenant\Shipping\Enums\ShippingFulfilment::OwnDelivery, 'Same day'), isTest: true)))->load('lines');
        $shipment = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 1], AccountReference::to($this->owner));

        return app(AssignDriver::class)->handle($this->owner, $shipment, $this->driver);
    });

    expect(driverAppRequest('GET', "deliveries/{$delivery->public_id}", token: $this->token)->json('data'))->toMatchArray(['is_test' => true, 'cash_to_collect' => null]);
    deliverWithCash($delivery->public_id, 5000, $this->token)->assertConflict()->assertJsonPath('code', 'payment_not_awaiting_receipt');
    driverAppRequest('POST', "deliveries/{$delivery->public_id}/delivered", ['recipient_name' => 'Ada Obi'], $this->token, headers: ['Idempotency-Key' => 'delivered-test-order'])->assertOk();

    expect($this->store->run(static fn (): int => DriverCashEntry::query()->count()))->toBe(0);
});

it('records a driver handing cash over, never more than they hold in that currency', function (): void {
    $delivery = cashDelivery($this->store, $this->owner, $this->driver);
    $due = cashToCollect($this->store, $delivery);
    deliverWithCash($delivery->public_id, $due, $this->token)->assertOk();
    $currency = $this->store->run(static fn (): string => DriverCashEntry::query()->sole()->cash_currency);
    $cashier = deliveryStaffWith($this->store, ['delivery.view', 'delivery.cash.manage']);
    $handover = fn (int $amount, string $key, string $in = '') => deliveryRequest('POST', "drivers/{$this->driver->public_id}/cash-handovers", ['amount' => $amount, 'currency' => $in ?: $currency], $cashier, headers: ['Idempotency-Key' => $key]);

    $first = $handover(1000, 'handover-key-1')->assertCreated()->assertJsonPath('data.type', 'handed_over')->assertJsonPath('data.amount.amount', 1000)->json('data.id');
    // A retry with the same key gives back the same handover instead of recording it twice.
    expect($handover(1000, 'handover-key-1')->assertCreated()->json('data.id'))->toBe($first);
    $handover($due, 'handover-key-2')->assertConflict()->assertJsonPath('code', 'cash_handover_exceeds_balance');
    $handover(1, 'handover-key-3', $currency === 'USD' ? 'EUR' : 'USD')->assertConflict()->assertJsonPath('code', 'cash_handover_exceeds_balance');
    $handover(0, 'handover-key-4')->assertUnprocessable()->assertJsonValidationErrors('amount');
    deliveryRequest('POST', "drivers/{$this->driver->public_id}/cash-handovers", ['amount' => 1, 'currency' => $currency], $cashier)->assertBadRequest()->assertJsonPath('code', 'idempotency_key_missing');
    $handover($due - 1000, 'handover-key-5')->assertCreated();

    expect(heldCash(deliveryRequest('GET', "drivers/{$this->driver->public_id}/cash", as: $cashier)))->toBe([['amount' => 0, 'currency' => $currency]]);
});

it('lets only staff with delivery.cash.manage record handovers, and staff with delivery.view see what drivers hold', function (): void {
    $viewer = deliveryStaffWith($this->store, ['delivery.view']);
    $nobody = deliveryStaffWith($this->store, []);

    deliveryRequest('GET', "drivers/{$this->driver->public_id}/cash", as: $viewer)->assertOk()->assertJsonPath('data.balances', []);
    deliveryRequest('GET', "drivers/{$this->driver->public_id}/cash-entries", as: $viewer)->assertOk();
    deliveryRequest('POST', "drivers/{$this->driver->public_id}/cash-handovers", ['amount' => 1, 'currency' => 'NGN'], $viewer, headers: ['Idempotency-Key' => 'handover-viewer'])->assertForbidden();
    deliveryRequest('GET', "drivers/{$this->driver->public_id}/cash", as: $nobody)->assertForbidden();
    driverAppRequest('GET', 'cash')->assertUnauthorized();
});

it('keeps what a driver holds per currency, never adding currencies together', function (): void {
    $naira = cashDelivery($this->store, $this->owner, $this->driver);
    $dollars = cashDelivery($this->store, $this->owner, $this->driver, 'USD');
    [$nairaDue, $dollarsDue] = [cashToCollect($this->store, $naira), cashToCollect($this->store, $dollars)];
    deliverWithCash($naira->public_id, $nairaDue, $this->token)->assertOk();
    deliverWithCash($dollars->public_id, $dollarsDue, $this->token)->assertOk();

    $balances = heldCash(driverAppRequest('GET', 'cash', token: $this->token));
    expect($balances)->toBe([['amount' => $nairaDue, 'currency' => 'NGN'], ['amount' => $dollarsDue, 'currency' => 'USD']]);

    // More dollars than the driver holds is refused, however much naira they hold.
    $this->store->run(function () use ($balances): void {
        expect(fn () => app(RecordCashHandover::class)->handle($this->owner, $this->driver, Money::ofMinor($balances[1]['amount'] + 1, 'USD')))->toThrow(CashHandoverExceedsBalanceException::class);
    });
});

it('makes two handovers recorded at the same moment take the same cash once', function (): void {
    $delivery = cashDelivery($this->store, $this->owner, $this->driver);
    $due = cashToCollect($this->store, $delivery);
    deliverWithCash($delivery->public_id, $due, $this->token)->assertOk();

    $this->store->run(function () use ($due): void {
        $currency = DriverCashEntry::query()->sole()->cash_currency;

        // Another member of staff holds the driver and has recorded all of it handed over, not yet committed.
        $other = SecondConnection::open()->holdRowLock($this->driver);
        $other->run(fn () => DB::connection(SecondConnection::NAME)->table('driver_cash_entries')->insert([
            'public_id' => strtolower((string) Str::ulid()), 'driver_id' => $this->driver->id, 'type' => 'handed_over', 'cash_amount' => $due, 'cash_currency' => $currency,
            'recorded_by_type' => 'staff_member', 'recorded_by_id' => $this->owner->public_id, 'created_at' => now(),
        ]));

        $handover = fn () => app(RecordCashHandover::class)->handle($this->owner, $this->driver, Money::ofMinor($due, $currency));
        expect($other->blockedStatement($handover))->toContain('for update');
        $other->commit();

        expect($handover)->toThrow(CashHandoverExceedsBalanceException::class)
            ->and(app(DriverCashLedger::class)->balanceIn($this->driver, $currency))->toBe(0);
    });
});

it('never changes or removes a ledger entry', function (): void {
    $delivery = cashDelivery($this->store, $this->owner, $this->driver);
    deliverWithCash($delivery->public_id, cashToCollect($this->store, $delivery), $this->token)->assertOk();

    $this->store->run(static function (): void {
        expect(static fn () => DB::table('driver_cash_entries')->update(['cash_amount' => 1]))->toThrow(QueryException::class, 'append-only')
            ->and(static fn () => DB::table('driver_cash_entries')->delete())->toThrow(QueryException::class, 'append-only')
            ->and(static fn () => DB::table('driver_cash_entries')->insert([
                'public_id' => strtolower((string) Str::ulid()), 'driver_id' => DriverCashEntry::query()->value('driver_id'), 'type' => 'collected', 'cash_amount' => 5, 'cash_currency' => 'NGN',
                'recorded_by_type' => 'driver', 'recorded_by_id' => 'x', 'created_at' => now(),
            ]))->toThrow(QueryException::class, 'driver_cash_entries_collected_on_delivery');
    });
});

it('includes the ledger in the driver\'s personal data, and keeps it when the driver is erased', function (): void {
    $delivery = cashDelivery($this->store, $this->owner, $this->driver);
    $due = cashToCollect($this->store, $delivery);
    deliverWithCash($delivery->public_id, $due, $this->token)->assertOk();

    deliveryRequest('GET', "drivers/{$this->driver->public_id}/personal-data", as: $this->owner)->assertOk()->assertJsonPath('data.cash.0.amount', $due)->assertJsonPath('data.cash.0.delivery', $delivery->public_id);
    deliveryRequest('POST', "drivers/{$this->driver->public_id}/erasure", ['password' => DELIVERY_STAFF_PASSWORD], $this->owner)->assertOk();

    expect($this->store->run(fn (): array => [DriverCashEntry::query()->sole()->driver_id, $this->driver->refresh()->isErased()]))->toBe([$this->driver->id, true]);
    deliveryRequest('GET', "drivers/{$this->driver->public_id}/cash", as: $this->owner)->assertJsonPath('data.balances.0.amount', $due);
});
