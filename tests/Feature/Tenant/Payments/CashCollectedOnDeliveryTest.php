<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\FindCashToCollect;
use App\Tenant\Payments\Actions\RecordCashCollectedOnDelivery;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Exceptions\PaymentNotAwaitingReceiptException;
use App\Tenant\Payments\Exceptions\PaymentReceiptInvalidException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 17: a driver records the cash they collected for an order paid on
 * delivery. It counts as the order's pending cash payment received,
 * confirmed by the driver, under the order's lock taken before the
 * payment's, so it and staff recording the same cash happen one after the
 * other. Delivery asks first how much is to be collected.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner] = $this->store->run(static fn (): array => orderStaff());
    Queue::fake([CheckPaymentWithGateway::class]);
    $this->driver = $this->store->run(static fn (): Driver => Driver::factory()->create());
});

afterEach(function (): void {
    deleteAllStores();
});

it('pays the order with the cash a driver collected, saying the driver confirmed it', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);

    $this->store->run(function () use ($order, $payment): void {
        expect(app(FindCashToCollect::class)->handle($order)?->minorAmount())->toBe($payment->amount->minorAmount());

        $paid = app(RecordCashCollectedOnDelivery::class)->handle($this->driver, $order, $payment->amount);

        expect($paid->id)->toBe($payment->id)
            ->and([$paid->status->value, $paid->confirmed_by_type, $paid->confirmed_by_id])->toBe(['succeeded', 'driver', $this->driver->public_id])
            ->and($order->refresh()->payment_status->value)->toBe('paid')
            ->and(app(FindCashToCollect::class)->handle($order))->toBeNull()
            ->and(Activity::query()->inLog('payments')->where('event', 'cash_collected')->sole()->causer_type)->toBe('driver')
            // Already collected: nothing is pending, so recording it again is refused.
            ->and(fn () => app(RecordCashCollectedOnDelivery::class)->handle($this->driver, $order, $payment->amount))->toThrow(PaymentNotAwaitingReceiptException::class);
    });
});

it('leaves the rest to collect when the driver collected less', function (): void {
    [$order] = manuallyPaidOrder(PaymentMethod::CashOnDelivery, 10000);

    $this->store->run(function () use ($order): void {
        $currency = $order->total->currency();
        app(RecordCashCollectedOnDelivery::class)->handle($this->driver, $order, Money::ofMinor(4000, $currency));

        expect(app(FindCashToCollect::class)->handle($order)?->minorAmount())->toBe($order->total->minorAmount() - 4000)
            ->and($order->refresh()->payment_status->value)->toBe('partially_paid');
    });
});

it('refuses cash that isn\'t a positive amount in the order\'s currency, and an order with no cash to collect', function (): void {
    [$onDelivery] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);
    [$byTransfer] = manuallyPaidOrder(PaymentMethod::BankTransfer);

    $this->store->run(function () use ($onDelivery, $byTransfer): void {
        $record = fn (Order $order, Money $amount): Payment => app(RecordCashCollectedOnDelivery::class)->handle($this->driver, $order, $amount);
        $currency = $onDelivery->total->currency();

        expect(static fn () => $record($onDelivery, Money::ofMinor(0, $currency)))->toThrow(PaymentReceiptInvalidException::class)
            ->and(static fn () => $record($onDelivery, Money::ofMinor(100, $currency === 'USD' ? 'EUR' : 'USD')))->toThrow(PaymentReceiptInvalidException::class)
            ->and(static fn () => $record($byTransfer, Money::ofMinor(100, $currency)))->toThrow(PaymentNotAwaitingReceiptException::class)
            ->and(app(FindCashToCollect::class)->handle($byTransfer))->toBeNull()
            // A test order is never paid in cash, so there is never cash to collect for one.
            ->and(app(FindCashToCollect::class)->handle(unpaidOrder(isTest: true)))->toBeNull();
    });
});

it('makes a driver and staff recording the same cash at once happen one after the other, so it counts once', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);

    $this->store->run(function () use ($order, $payment): void {
        // Staff are recording the cash: they hold the order, not yet committed.
        $other = SecondConnection::open()->holdRowLock($order);
        $collect = fn (): Payment => app(RecordCashCollectedOnDelivery::class)->handle($this->driver, $order, $payment->amount);

        expect($other->blockedStatement($collect))->toContain('for update');
        $other->commit();
    });

    recordReceived($payment, $payment->amount->minorAmount());

    $this->store->run(function () use ($order, $payment): void {
        expect(fn () => app(RecordCashCollectedOnDelivery::class)->handle($this->driver, $order, $payment->amount))->toThrow(PaymentNotAwaitingReceiptException::class)
            ->and(Payment::query()->where('order_id', $order->id)->where('status', 'succeeded')->count())->toBe(1);
    });
});
