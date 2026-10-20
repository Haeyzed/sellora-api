<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Shared\Money\Money;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Payments\Actions\CheckPendingRefund;
use App\Tenant\Payments\Actions\DisconnectPaymentGateway;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Actions\VerifyRefund;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Events\RefundSucceeded;
use App\Tenant\Payments\Exceptions\OrderNotRefundableException;
use App\Tenant\Payments\Exceptions\PartialRefundNotSupportedException;
use App\Tenant\Payments\Exceptions\PaymentGatewayInUseException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Exceptions\RefundExceedsRefundableException;
use App\Tenant\Payments\Exceptions\RefundInvalidException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\Models\RefundLine;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15.5: refunds. Staff give back an amount, or lines and shipping at
 * what the buyer paid for them, tax included, split by largest remainder.
 * A refund draws on the payment named, or the newest succeeded payments
 * first; a payment in review only when named, for all it received. Never
 * more than was paid less what is refunded or on its way back, read under
 * the order's lock and then the payments'. Each part goes back the way its
 * payment came: through its gateway (fail-closed, checked until it settles,
 * sent again if the gateway never got it), or recorded as paid back by hand.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    // Each payment's and refund's own checks are run by hand where they matter.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('gives back an amount through the payment\'s gateway, with its own reference as the idempotency key, and the order follows', function (): void {
    [$order, $payment] = gatewayPaidOrder();

    $refund = refundOrder($order, 4000);

    $this->store->run(function () use ($refund, $order, $payment): void {
        $part = $refund->paymentRefunds->sole();
        expect($refund->amount->minorAmount())->toBe(4000)
            ->and($refund->only(['reason', 'requested_by_type', 'requested_by_id']))->toBe(['reason' => 'Changed their mind', 'requested_by_type' => 'staff_member', 'requested_by_id' => $this->owner->public_id])
            ->and([$part->payment_id, $part->status->value, $part->amount->minorAmount(), $part->recorded_by_type])->toBe([$payment->id, 'succeeded', 4000, null])
            ->and($part->reference)->toStartWith('rfd_')
            ->and($part->gateway_reference)->toBe('fake_'.$part->reference)
            ->and($this->script->timesCalled('refund', $part->reference))->toBe(1)
            ->and(paymentHistory($payment))->toBe(['started', 'succeeded', 'refund_started', 'refunded'])
            ->and($order->refresh()->payment_status->value)->toBe('partially_refunded')
            ->and(Activity::query()->inLog('payments')->pluck('description')->last())->toStartWith('Refunded part or all of order');
        Queue::assertPushed(CheckRefundWithGateway::class, static fn (CheckRefundWithGateway $job): bool => $job->paymentRefundId === $part->id && $job->checkNumber === 0);
    });

    refundOrder($order, 7500);
    expect($this->store->run(static fn (): string => $order->refresh()->payment_status->value))->toBe('refunded');
});

it('gives back lines at what the buyer paid for them, tax included, a few units at a time adding up exactly', function (): void {
    $variant = $this->store->run(static function () {
        addTaxRate('Standard', 'NG', null, '7.5');

        return cartVariant(1000, stock: 5);
    });
    [$order] = gatewayPaidOrder([[$variant, 3]]);
    $line = $this->store->run(static fn () => $order->lines()->sole());

    $first = refundOrder($order, lines: [$line->public_id => 1]);
    $rest = refundOrder($order, lines: [$line->public_id => 2], shipping: true);

    $this->store->run(function () use ($first, $rest, $line, $order): void {
        $shipping = $order->shippingLine()->sole();
        $firstLine = $first->lines->sole();
        $restLine = $rest->lines->sole();
        // ₦30 including 7.5% VAT is ₦2.09 of tax: 0.70, 0.70 and 0.69 per unit, the first units taking the leftover.
        expect($line->tax->minorAmount())->toBe(209)
            ->and([$firstLine->quantity, $firstLine->amount->minorAmount(), $firstLine->tax->minorAmount()])->toBe([1, 1000, 70])
            ->and([$restLine->quantity, $restLine->amount->minorAmount(), $restLine->tax->minorAmount()])->toBe([2, 2000, 139])
            ->and($first->amount->minorAmount())->toBe(1000)
            ->and([$rest->amount->minorAmount(), $rest->shipping->minorAmount(), $rest->shipping_tax->minorAmount()])->toBe([2000 + $shipping->total->minorAmount(), $shipping->total->minorAmount(), $shipping->tax->minorAmount()])
            ->and($first->amount->add($rest->amount)->isEqualTo($order->total))->toBeTrue()
            ->and($order->refresh()->payment_status->value)->toBe('refunded');
    });
});

it('refuses a refund whose parts don\'t fit the order', function (Closure $refund): void {
    $variant = $this->store->run(static fn () => cartVariant(1000, stock: 5));
    [$order, $payment] = gatewayPaidOrder([[$variant, 2]]);
    [$other, $otherPayment] = gatewayPaidOrder();
    [$line, $otherLine] = $this->store->run(static fn (): array => [$order->lines()->sole()->public_id, $other->lines()->sole()->public_id]);
    refundOrder($order, lines: [$line => 1], shipping: true);

    expect(fn () => $refund($order, $line, $otherLine, $otherPayment, $payment))->toThrow(RefundInvalidException::class)
        ->and($this->store->run(static fn (): int => Refund::query()->count()))->toBe(1);
})->with([
    'nothing to give back' => [static fn ($order) => refundOrder($order)],
    'an amount and lines' => [static fn ($order, $line) => refundOrder($order, 500, lines: [$line => 1])],
    'no units' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 0])],
    'more units than are left' => [static fn ($order, $line) => refundOrder($order, lines: [$line => 2])],
    'another order\'s line' => [static fn ($order, $line, $otherLine) => refundOrder($order, lines: [$otherLine => 1])],
    'shipping again' => [static fn ($order) => refundOrder($order, shipping: true)],
    'a negative amount' => [static fn ($order) => refundOrder($order, -100)],
    'another order\'s payment' => [static fn ($order, $line, $otherLine, $otherPayment) => refundOrder($order, 100, from: $otherPayment)],
    'an amount in another currency' => [static fn ($order) => test()->store->run(static fn () => app(RefundOrder::class)->handle(test()->owner, $order, new RefundRequest(Money::ofMinor(100, 'USD'))))],
    'an amount in another currency from its payment' => [static fn ($order, $line, $otherLine, $otherPayment, $payment) => test()->store->run(static fn () => app(RefundOrder::class)->handle(test()->owner, $order, new RefundRequest(Money::ofMinor(100, 'USD'), paymentId: $payment->public_id)))],
]);

it('frees the units and shipping of a refund that failed entirely, so they can be given back again', function (): void {
    $variant = $this->store->run(static fn () => cartVariant(1000, stock: 5));
    [$order] = gatewayPaidOrder([[$variant, 1]]);
    $line = $this->store->run(static fn (): string => $order->lines()->sole()->public_id);
    $this->script->rejectsRefunds = true;
    refundOrder($order, lines: [$line => 1], shipping: true);
    $this->script->rejectsRefunds = false;

    $again = refundOrder($order, lines: [$line => 1], shipping: true);

    expect([$again->paymentRefunds->sole()->status->value, $again->lines->sole()->quantity, $again->shipping->isPositive()])->toBe(['succeeded', 1, true]);
});

it('never gives back more than was paid, counting refunds on their way back and not failed ones', function (): void {
    // ₦115 with shipping.
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    refundOrder($order, 6000);

    expect(fn () => refundOrder($order, 5501))->toThrow(RefundExceedsRefundableException::class);

    $this->script->refundAnswer = null;
    $this->script->rejectsRefunds = true;
    refundOrder($order, 5500);
    $this->script->rejectsRefunds = false;

    expect(refundOrder($order, 5500)->paymentRefunds->sole()->status->value)->toBe('succeeded')
        ->and(fn () => refundOrder($order, 1))->toThrow(RefundExceedsRefundableException::class);
});

it('reads what is left to give back only under the order\'s lock, so two refunds at once never give back more than was paid', function (): void {
    [$order, $payment] = gatewayPaidOrder();

    $this->store->run(function () use ($order, $payment): void {
        $other = SecondConnection::open()->holdRowLock($order);
        DB::enableQueryLog();
        expect($other->blockedStatement(fn () => app(RefundOrder::class)->handle($this->owner, $order, new RefundRequest(Money::ofMinor(6000, 'NGN')))))->toContain('"orders"')->toContain('for update')
            // It waits before reading anything about the payments.
            ->and(collect(DB::getQueryLog())->pluck('query')->filter(static fn (string $query): bool => str_contains($query, 'payment'))->all())->toBe([]);
        DB::disableQueryLog();

        // Meanwhile the other request gives back ₦60 and commits.
        $other->run(static function (Connection $db) use ($order, $payment): void {
            $refundId = $db->table('refunds')->insertGetId(['public_id' => Str::lower((string) Str::ulid()), 'order_id' => $order->id, 'currency' => 'NGN', 'amount_amount' => 6000, 'requested_by_type' => 'staff_member', 'requested_by_id' => 'someone', 'created_at' => now()]);
            $db->table('payment_refunds')->insert(['public_id' => Str::lower((string) Str::ulid()), 'refund_id' => $refundId, 'payment_id' => $payment->id, 'status' => 'pending', 'currency' => 'NGN', 'amount_amount' => 6000, 'reference' => 'rfd_other', 'created_at' => now(), 'updated_at' => now()]);
        });
        $other->commit();

        expect(fn () => app(RefundOrder::class)->handle($this->owner, $order, new RefundRequest(Money::ofMinor(6000, 'NGN'))))->toThrow(RefundExceedsRefundableException::class)
            ->and(app(RefundOrder::class)->handle($this->owner, $order, new RefundRequest(Money::ofMinor(4000, 'NGN')))->amount->minorAmount())->toBe(4000);
    });
});

it('takes the payment\'s lock after the order\'s, so it waits for a payment being confirmed', function (): void {
    [$order, $payment] = gatewayPaidOrder();

    $this->store->run(function () use ($order, $payment): void {
        $other = SecondConnection::open()->holdRowLock($payment);
        expect($other->blockedStatement(fn () => app(RefundOrder::class)->handle($this->owner, $order, new RefundRequest(Money::ofMinor(1000, 'NGN')))))->toContain('"payments"')->toContain('for update');
        $other->rollBack();
    });
});

it('settles a refund the gateway answers about under the order\'s lock, so it waits for a refund or confirmation under way', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    $part = refundOrder($order, 4000)->paymentRefunds->sole();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(4000, 'NGN'));

    $this->store->run(static function () use ($order, $part): void {
        $other = SecondConnection::open()->holdRowLock($order);
        $part->refresh();
        DB::enableQueryLog();
        expect($other->blockedStatement(static fn () => app(VerifyRefund::class)->handle($part)))->toContain('"orders"')->toContain('for update')
            // It waits before changing the refund.
            ->and(collect(DB::getQueryLog())->pluck('query')->filter(static fn (string $query): bool => str_contains($query, 'update "payment_refunds"'))->all())->toBe([]);
        DB::disableQueryLog();
        $other->rollBack();
        expect(app(VerifyRefund::class)->handle($part->refresh())->status->value)->toBe('succeeded');
    });
});

it('draws on the newest succeeded payments first, or only on the one staff name, recording payments made by hand as paid back by hand', function (): void {
    [$order, $first] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    recordReceived($first, 4000);
    $rest = $this->store->run(static fn (): Payment => Payment::query()->where('order_id', $order->id)->where('status', 'pending')->sole());
    recordReceived($rest, 7500);

    // Named: from the older payment, though the newer one still has money.
    expect(refundOrder($order, 1000, from: $first)->paymentRefunds->sole()->payment_id)->toBe($first->id);

    $refund = refundOrder($order, 8000);

    expect($refund->paymentRefunds->map(static fn (PaymentRefund $part): array => [$part->payment_id, $part->amount->minorAmount(), $part->status->value, $part->recorded_by_id])->all())
        ->toBe([[$rest->id, 7500, 'succeeded', $this->owner->public_id], [$first->id, 500, 'succeeded', $this->owner->public_id]])
        ->and($this->script->timesCalled('refund'))->toBe(0)
        ->and($this->store->run(static fn (): string => $order->refresh()->payment_status->value))->toBe('partially_refunded')
        ->and(fn () => refundOrder($order, 2501, from: $first))->toThrow(RefundExceedsRefundableException::class)
        ->and(refundOrder($order, 2500, from: $first)->paymentRefunds->sole()->payment_id)->toBe($first->id);
});

it('refunds money in review only when its payment is named, for all it received, and it never counts toward the order', function (): void {
    $payment = $this->store->run(static function (): Payment {
        $payment = startFakePayment(unpaidOrder(10000));
        gatewayAnswers($payment, GatewayPaymentState::Succeeded, Money::ofMinor(9000, 'NGN'), GatewayMode::Live);

        return verifyFakePayment($payment);
    });
    $order = $this->store->run(static fn () => $payment->order);

    expect($payment->status->value)->toBe('needs_review')
        ->and(fn () => refundOrder($order, 9000))->toThrow(OrderNotRefundableException::class)
        ->and(fn () => refundOrder($order, 5000, from: $payment))->toThrow(RefundInvalidException::class);

    // The order is then paid in full by another payment: refunds not naming the one in review never draw on it.
    $this->store->run(static function () use ($order): void {
        $paid = startFakePayment($order->refresh());
        gatewayAnswers($paid, GatewayPaymentState::Succeeded, $paid->amount, GatewayMode::Live);
        verifyFakePayment($paid);
    });
    expect(fn () => refundOrder($order, $order->total->minorAmount() + 1))->toThrow(RefundExceedsRefundableException::class);

    $part = refundOrder($order, 9000, from: $payment)->paymentRefunds->sole();

    expect([$part->status->value, $part->amount->minorAmount()])->toBe(['succeeded', 9000])
        ->and($this->store->run(static fn (): string => $order->refresh()->payment_status->value))->toBe('paid')
        ->and(fn () => refundOrder($order, 9000, from: $payment))->toThrow(RefundExceedsRefundableException::class);
});

it('refunds an open order only once paid in full, and a cancelled one whatever it holds, until it owes nothing', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    recordReceived($payment, 4000);

    expect(fn () => refundOrder($order, 1000))->toThrow(OrderNotRefundableException::class);

    $this->store->run(fn () => app(CancelOrder::class)->byStaff($this->owner, $order));
    expect($this->store->run(static fn (): bool => $order->refresh()->refund_due))->toBeTrue();

    refundOrder($order, 1000);
    expect($this->store->run(static fn (): array => [$order->refresh()->payment_status->value, $order->refund_due]))->toBe(['partially_refunded', true]);

    refundOrder($order, 3000);
    expect($this->store->run(static fn (): array => [$order->refresh()->payment_status->value, $order->refund_due]))->toBe(['refunded', false])
        ->and(fn () => refundOrder($order, 1))->toThrow(RefundExceedsRefundableException::class);

    expect(fn () => refundOrder($this->store->run(static fn () => unpaidOrder()), 1000))->toThrow(OrderNotRefundableException::class);
});

it('leaves an overpaid order paid when only what was paid over is given back', function (): void {
    [$order, $payment] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    // ₦120 received of ₦115.
    recordReceived($payment, 12000);

    refundOrder($order, 500);
    expect($this->store->run(static fn (): string => $order->refresh()->payment_status->value))->toBe('paid');

    refundOrder($order, 1);
    expect($this->store->run(static fn (): string => $order->refresh()->payment_status->value))->toBe('partially_refunded');
});

it('keeps a refund pending until the gateway says it went through with exactly the amount asked', function (): void {
    [$order] = gatewayPaidOrder();
    $log = Log::spy();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r');
    $part = refundOrder($order, 4000)->paymentRefunds->sole();

    expect($part->status->value)->toBe('pending');

    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(3999, 'NGN'));
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    expect($this->store->run(static fn (): string => $part->refresh()->status->value))->toBe('pending');
    $log->shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'another amount') && $context['refund'] === $part->public_id)->once();

    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(4000, 'USD'));
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    expect($this->store->run(static fn (): string => $part->refresh()->status->value))->toBe('pending');

    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(4000, 'NGN'));
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    expect($this->store->run(static fn (): array => [$part->refresh()->status->value, $order->refresh()->payment_status->value]))->toBe(['succeeded', 'partially_refunded']);
});

it('checks a refund pending at the gateway on its own schedule until it settles', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    $part = refundOrder($order, 4000)->paymentRefunds->sole();

    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh(), 0));
    Queue::assertPushed(CheckRefundWithGateway::class, static fn (CheckRefundWithGateway $job): bool => $job->paymentRefundId === $part->id && $job->checkNumber === 1);
    // Money on its way back doesn't count as refunded yet.
    expect($this->store->run(static fn (): array => [$part->refresh()->status->value, $part->checks, $order->refresh()->payment_status->value]))->toBe(['pending', 2, 'paid']);

    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(4000, 'NGN'));
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh(), 1));

    expect($this->store->run(static fn (): string => $part->refresh()->status->value))->toBe('succeeded');
    Queue::assertNotPushed(CheckRefundWithGateway::class, static fn (CheckRefundWithGateway $job): bool => $job->checkNumber === 2);
});

it('sends a refund again, with the same reference, when the gateway never got it', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->unavailable = true;
    $part = refundOrder($order, 4000)->paymentRefunds->sole();
    $this->script->unavailable = false;

    expect($part->status->value)->toBe('pending');

    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));

    expect($this->store->run(static fn (): string => $part->refresh()->status->value))->toBe('succeeded')
        ->and($this->script->timesCalled('refund', $part->reference))->toBe(2)
        ->and($this->script->timesCalled('refund'))->toBe(2);
});

it('marks a refund the gateway refuses as failed, tells staff, and frees what it held', function (): void {
    Event::fake([RefundFailed::class]);
    [$order, $payment] = gatewayPaidOrder();
    $this->script->rejectsRefunds = true;

    $part = refundOrder($order, 11500)->paymentRefunds->sole();

    expect([$part->status->value, $part->failure_code])->toBe(['failed', 'refund_refused'])
        ->and($this->store->run(static fn (): array => [$order->refresh()->payment_status->value, paymentHistory($payment)]))->toBe(['paid', ['started', 'succeeded', 'refund_started', 'refund_failed']]);
    Event::assertDispatched(RefundFailed::class, static fn (RefundFailed $event): bool => $event->paymentRefund->is($part));

    // A gateway that refunds after saying it failed is believed: the money went back.
    $this->script->refunds[$part->reference] = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_late', Money::ofMinor(11500, 'NGN'));
    sendFakeWebhook(['id' => 'evt_refund', 'type' => 'refund', 'reference' => $part->reference])->assertOk();

    expect($this->store->run(static fn (): array => [$part->refresh()->status->value, $order->refresh()->payment_status->value]))->toBe(['succeeded', 'refunded']);
});

it('asks the gateway about the refund a webhook names, only through the connection it came to', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    $part = refundOrder($order, 4000)->paymentRefunds->sole();
    connectFakeGateway('live', gateway: 'local_fake_gateway');
    $this->script->refunds[$part->reference] = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(4000, 'NGN'));

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'refund', 'reference' => $part->reference], fakeWebhookEndpoint('local_fake_gateway'))->assertOk();
    expect($this->store->run(static fn (): string => $part->refresh()->status->value))->toBe('pending');

    sendFakeWebhook(['id' => 'evt_2', 'type' => 'refund', 'reference' => $part->reference])->assertOk();
    expect($this->store->run(static fn (): string => $part->refresh()->status->value))->toBe('succeeded');
});

it('keeps refunding money already taken whatever the plan, and while the store is suspended', function (string $state): void {
    [$order] = gatewayPaidOrder();
    $state === 'store suspended' ? $this->store->update(['status' => TenantStatus::Suspended]) : putFakeGatewayInState($state);

    expect(refundOrder($order, 4000)->paymentRefunds->sole()->status->value)->toBe('succeeded');
})->with(['locked', 'disabled', 'suspended', 'store suspended']);

it('keeps the gateway connected while a refund is on its way back, and refunds only through a connected gateway', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    $part = refundOrder($order, 4000)->paymentRefunds->sole();

    expect(fn () => $this->store->run(fn () => app(DisconnectPaymentGateway::class)->handle($this->owner, 'fake_gateway')))->toThrow(PaymentGatewayInUseException::class);

    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(4000, 'NGN'));
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    $this->store->run(fn () => app(DisconnectPaymentGateway::class)->handle($this->owner, 'fake_gateway'));

    expect(fn () => refundOrder($order, 1000))->toThrow(PaymentGatewayNotConnectedException::class);
});

it('gives back part of a payment only through a gateway that refunds part of one', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->partialRefunds = false;

    expect(fn () => refundOrder($order, 4000))->toThrow(PartialRefundNotSupportedException::class)
        ->and(refundOrder($order, 11500)->paymentRefunds->sole()->status->value)->toBe('succeeded');
});

it('returns the same refund for the same Idempotency-Key, giving back once', function (): void {
    [$order] = gatewayPaidOrder();

    $first = refundOrder($order, 4000, idempotencyKey: 'refund-1');
    $again = refundOrder($order, 4000, idempotencyKey: 'refund-1');

    expect($again->public_id)->toBe($first->public_id)
        ->and($this->script->timesCalled('refund'))->toBe(1)
        ->and($this->store->run(static fn (): int => Activity::query()->inLog('payments')->where('event', 'refund_created')->count()))->toBe(1)
        ->and($this->store->run(static fn (): array => [Refund::query()->count(), PaymentRefund::query()->count()]))->toBe([1, 1]);
});

it('asks again in the hourly sweep about gateway refunds no one asked about for a while', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    $part = refundOrder($order, 4000)->paymentRefunds->sole();

    $this->artisan('payments:check-pending')->assertSuccessful();
    Queue::assertNotPushed(CheckRefundWithGateway::class, static fn (CheckRefundWithGateway $job): bool => $job->checkNumber === null);

    $this->travel(config()->integer('payments.checks.sweep_after_minutes') + 1)->minutes();
    $this->artisan('payments:check-pending')->assertSuccessful();

    Queue::assertPushed(CheckRefundWithGateway::class, static fn (CheckRefundWithGateway $job): bool => $job->paymentRefundId === $part->id && $job->checkNumber === null);
});

it('announces each part that went back inside the transaction, with the order\'s totals', function (): void {
    [$order] = gatewayPaidOrder();
    $seen = [];
    Event::listen(RefundSucceeded::class, static function (RefundSucceeded $event) use (&$seen): void {
        $seen[] = [DB::transactionLevel() > 0, $event->totals->paid->minorAmount(), $event->totals->refunded->minorAmount()];
    });
    // Money still on its way back isn't counted as refunded.
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    refundOrder($order, 2000);
    $this->script->refundAnswer = null;

    refundOrder($order, 4000);

    expect($seen)->toBe([[true, 11500, 4000]]);
});

it('keeps refunds as financial records: written once, and a part only moves forward', function (): void {
    $variant = $this->store->run(static fn () => cartVariant(1000, stock: 5));
    [$order] = gatewayPaidOrder([[$variant, 2]]);
    $line = $this->store->run(static fn () => $order->lines()->sole());
    $refund = refundOrder($order, lines: [$line->public_id => 1]);

    $this->store->run(static function () use ($refund): void {
        $part = $refund->paymentRefunds->sole();

        expect(static fn () => DB::table('refunds')->where('id', $refund->id)->update(['amount_amount' => 1]))->toThrow(QueryException::class, 'financial records')
            ->and(static fn () => DB::table('refund_lines')->where('refund_id', $refund->id)->delete())->toThrow(QueryException::class, 'financial records')
            ->and(static fn () => DB::statement('truncate refunds cascade'))->toThrow(QueryException::class, 'financial records')
            ->and(static fn () => DB::statement('truncate payment_refunds'))->toThrow(QueryException::class, 'financial records')
            ->and(static fn () => DB::statement('truncate refund_lines'))->toThrow(QueryException::class, 'financial records')
            ->and(static fn () => DB::table('payment_refunds')->where('id', $part->id)->update(['amount_amount' => 1]))->toThrow(QueryException::class, 'never change')
            ->and(static fn () => DB::table('payment_refunds')->where('id', $part->id)->update(['status' => 'pending', 'succeeded_at' => null]))->toThrow(QueryException::class, 'can\'t go from succeeded to pending')
            ->and(static fn () => DB::table('payment_refunds')->where('id', $part->id)->delete())->toThrow(QueryException::class, 'financial records')
            ->and(RefundLine::query()->count())->toBe(1);
    });
});
