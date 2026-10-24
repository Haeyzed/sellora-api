<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\RecordOrderPayments;
use App\Tenant\Orders\Events\OrderPaidAfterCancellation;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Events\PaymentFailed;
use App\Tenant\Payments\Events\PaymentNeedsReview;
use App\Tenant\Payments\Exceptions\OrderNotPayableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotAvailableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Exceptions\PaymentInProgressException;
use App\Tenant\Payments\Exceptions\PaymentRefusedByGatewayException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15.2: gateway payments, and the fail-closed rule. A payment is paid
 * only when the gateway, asked server-side, reports success with exactly
 * the amount, currency and mode expected. Anything missing stays pending;
 * anything different goes to review and never counts. Test payments never
 * pay live orders, nor live payments test orders. The order's payment
 * status moves with its payments, in the same transaction.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    // Each payment's own checks with the gateway are tested in PaymentChecksTest.
    Queue::fake([CheckPaymentWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('starts a payment of what is left on the order on the gateway\'s hosted page, with our reference as its idempotency key', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder(10000);
        $payment = startFakePayment($order);

        expect($payment->only(['method', 'gateway', 'is_test', 'currency']))->toMatchArray(['gateway' => 'fake_gateway', 'is_test' => false, 'currency' => 'NGN'])
            ->and($payment->method->value)->toBe('gateway')
            ->and($payment->status->value)->toBe('pending')
            ->and($payment->amount->isEqualTo($order->total))->toBeTrue()
            ->and($payment->reference)->toStartWith('pay_')
            ->and($payment->redirect_url)->toBe('https://pay.fake.test/'.$payment->reference)
            ->and($this->script->timesCalled('startPayment', $payment->reference))->toBe(1)
            ->and(PaymentEvent::query()->sole()->type->value)->toBe('started')
            ->and($order->refresh()->payment_status->value)->toBe('unpaid');
    });
});

it('marks a payment paid only when the gateway reports success with exactly the amount, currency and mode expected', function (Closure $answer, string $payment, string $order, ?string $reason): void {
    $this->store->run(function () use ($answer, $payment, $order, $reason): void {
        Event::fake([PaymentNeedsReview::class, PaymentFailed::class]);
        $placed = unpaidOrder(10000);
        $started = startFakePayment($placed);
        gatewayAnswers($started, ...$answer($placed->total));

        $verified = verifyFakePayment($started);

        expect([$verified->status->value, $verified->review_reason?->value, $placed->refresh()->payment_status->value])->toBe([$payment, $reason, $order]);
    });
})->with([
    'exactly as expected' => [static fn (Money $total): array => [GatewayPaymentState::Succeeded, $total, GatewayMode::Live], 'succeeded', 'paid', null],
    'no mode said: the connection\'s' => [static fn (Money $total): array => [GatewayPaymentState::Succeeded, $total, null], 'succeeded', 'paid', null],
    'success with no amount' => [static fn (Money $total): array => [GatewayPaymentState::Succeeded, null, GatewayMode::Live], 'pending', 'unpaid', null],
    'one minor unit short' => [static fn (Money $total): array => [GatewayPaymentState::Succeeded, $total->subtract(Money::ofMinor(1, 'NGN')), GatewayMode::Live], 'needs_review', 'unpaid', 'amount_mismatch'],
    'tampered upward' => [static fn (Money $total): array => [GatewayPaymentState::Succeeded, $total->add(Money::ofMinor(100000, 'NGN')), GatewayMode::Live], 'needs_review', 'unpaid', 'amount_mismatch'],
    'another currency' => [static fn (Money $total): array => [GatewayPaymentState::Succeeded, Money::ofMinor($total->minorAmount(), 'USD'), GatewayMode::Live], 'needs_review', 'unpaid', 'currency_mismatch'],
    'a test payment for a live order' => [static fn (Money $total): array => [GatewayPaymentState::Succeeded, $total, GatewayMode::Test], 'needs_review', 'unpaid', 'mode_mismatch'],
    'an answer it can\'t read' => [static fn (Money $total): array => [GatewayPaymentState::Unknown, $total, GatewayMode::Live], 'pending', 'unpaid', null],
    'still on its way' => [static fn (Money $total): array => [GatewayPaymentState::Pending, null, null], 'pending', 'unpaid', null],
    'failed' => [static fn (Money $total): array => [GatewayPaymentState::Failed, null, null], 'failed', 'unpaid', null],
]);

it('tells the gateway the amount the payment was started for when asking about it', function (): void {
    $this->store->run(function (): void {
        $payment = startFakePayment(unpaidOrder(10000));

        verifyFakePayment($payment);

        expect($this->script->expectedAmounts[$payment->reference]->isEqualTo($payment->amount))->toBeTrue()
            ->and($this->script->expectedAmounts[$payment->reference]->currency())->toBe($payment->amount->currency());
    });
});

it('takes a three-decimal currency to the last fils', function (): void {
    $this->store->run(function (): void {
        offerStoreCurrency('KWD', '0.002');
        $short = startFakePayment(unpaidOrder(12345, 'KWD'));
        $exact = startFakePayment(unpaidOrder(12345, 'KWD'));

        expect($short->amount->decimalPlaces())->toBe(3);
        gatewayAnswers($short, GatewayPaymentState::Succeeded, $short->amount->subtract(Money::ofMinor(1, 'KWD')), GatewayMode::Live);
        gatewayAnswers($exact, GatewayPaymentState::Succeeded, $exact->amount, GatewayMode::Live);

        expect(verifyFakePayment($short)->status->value)->toBe('needs_review')
            ->and(verifyFakePayment($exact)->status->value)->toBe('succeeded')
            ->and($exact->order->payment_status->value)->toBe('paid');
    });
});

it('never lets a test payment pay a live order, or a live payment a test order', function (): void {
    $this->store->run(function (): void {
        $live = unpaidOrder();
        $test = unpaidOrder(isTest: true);

        // The live connection takes only live orders; a test connection only test ones.
        expect(static fn () => startFakePayment($test))->toThrow(PaymentGatewayNotAvailableException::class);
    });

    connectFakeGateway('test', gateway: 'local_fake_gateway');
    $this->store->run(function (): void {
        [$live, $test] = Order::query()->orderBy('id')->get()->all();
        expect(static fn () => startFakePayment($live, gateway: 'local_fake_gateway'))->toThrow(PaymentGatewayNotAvailableException::class);

        $payment = startFakePayment($test, gateway: 'local_fake_gateway');
        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);

        expect($payment->is_test)->toBeTrue()
            ->and(verifyFakePayment($payment)->review_reason?->value)->toBe('mode_mismatch')
            ->and($test->refresh()->payment_status->value)->toBe('unpaid')
            ->and(static fn () => DB::table('orders')->where('id', $live->id)->update(['is_test' => true]))->toThrow(QueryException::class, 'test flag and way of paying never change');

        // Even a test payment that somehow ended up on the live order, and that the gateway confirms in test mode, never pays it.
        $stray = new Payment;
        $stray->forceFill(['order_id' => $live->id, 'method' => 'gateway', 'gateway' => 'local_fake_gateway', 'connection_id' => $payment->connection_id, 'is_test' => true, 'status' => 'pending', 'amount' => $live->total, 'reference' => 'pay_stray'])->save();
        gatewayAnswers($stray, GatewayPaymentState::Succeeded, $stray->amount, GatewayMode::Test);

        expect(verifyFakePayment($stray)->review_reason?->value)->toBe('mode_mismatch')
            ->and($live->refresh()->payment_status->value)->toBe('unpaid');
    });
});

it('never counts money in review, which stays apart while a later payment pays the order', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder(10000);
        $first = startFakePayment($order);
        gatewayAnswers($first, GatewayPaymentState::Succeeded, Money::ofMinor(500, 'NGN'), GatewayMode::Live);
        verifyFakePayment($first);

        $second = startFakePayment($order);
        expect($second->id)->not->toBe($first->id)
            ->and($second->amount->isEqualTo($order->total))->toBeTrue();
        gatewayAnswers($second, GatewayPaymentState::Succeeded, $second->amount, GatewayMode::Live);

        expect(verifyFakePayment($second)->status->value)->toBe('succeeded')
            ->and($order->refresh()->payment_status->value)->toBe('paid')
            ->and($first->refresh()->status->value)->toBe('needs_review')
            ->and($first->received?->minorAmount())->toBe(500);
    });
});

it('sends money taken after the gateway said it failed to review, never to the order', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder();
        $payment = startFakePayment($order);
        gatewayAnswers($payment, GatewayPaymentState::Failed);
        verifyFakePayment($payment);

        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);

        expect(verifyFakePayment($payment)->review_reason?->value)->toBe('succeeded_after_failing')
            ->and($order->refresh()->payment_status->value)->toBe('unpaid');
    });
});

it('tries one attempt at a time: the same attempt again, never a second one while it may go through', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder();
        $first = startFakePayment($order, 'checkout-key-1');

        expect(startFakePayment($order)->id)->toBe($first->id)
            ->and(startFakePayment($order, 'checkout-key-1')->id)->toBe($first->id)
            ->and($this->script->timesCalled('startPayment'))->toBe(1);

        connectFakeGateway('live', gateway: 'local_fake_gateway');
        expect(static fn () => startFakePayment($order, gateway: 'local_fake_gateway'))->toThrow(PaymentInProgressException::class);

        gatewayAnswers($first, GatewayPaymentState::Failed);
        verifyFakePayment($first);

        // The client's key still names that attempt once it has failed; only a new key, or none, starts another.
        expect(startFakePayment($order, 'checkout-key-1')->id)->toBe($first->id)
            ->and(startFakePayment($order)->id)->not->toBe($first->id);
    });
});

it('makes two starts at the same moment happen one after the other, so only one attempt is made', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder();
        $other = SecondConnection::open()->holdRowLock($order);

        expect($other->blockedStatement(static fn () => startFakePayment($order)))->toContain('"orders"')->toContain('for update');
        $other->rollBack();
        expect(Payment::query()->count())->toBe(0);
    });
});

it('starts payments only for an open order still waiting for payment, before its deadline', function (): void {
    $this->store->run(function (): void {
        $cancelled = unpaidOrder();
        DB::table('orders')->where('id', $cancelled->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'staff']);
        $late = unpaidOrder();

        expect(static fn () => startFakePayment($cancelled))->toThrow(OrderNotPayableException::class);

        $this->travel(61)->minutes();
        expect(static fn () => startFakePayment($late))->toThrow(OrderNotPayableException::class);
    });
});

it('fails a payment the gateway refuses to start, and leaves one it didn\'t answer pending for a later check', function (): void {
    $this->store->run(function (): void {
        $this->script->rejectsPayments = true;
        expect(static fn () => startFakePayment(unpaidOrder()))->toThrow(PaymentRefusedByGatewayException::class)
            ->and(Payment::query()->sole()->only(['failure_code']))->toBe(['failure_code' => 'invalid_request']);

        $this->script->rejectsPayments = false;
        $this->script->unavailable = true;
        $order = unpaidOrder();
        expect(static fn () => startFakePayment($order))->toThrow(PaymentGatewayUnreachableException::class);
        $pending = Payment::query()->where('order_id', $order->id)->sole();
        expect($pending->status->value)->toBe('pending');

        // Asking while it is still down changes nothing but when it was asked.
        expect(verifyFakePayment($pending)->only(['checks']))->toBe(['checks' => 1]);

        $this->script->unavailable = false;
        gatewayAnswers($pending, GatewayPaymentState::Succeeded, $pending->amount, GatewayMode::Live);
        expect(verifyFakePayment($pending)->status->value)->toBe('succeeded')
            ->and($order->refresh()->payment_status->value)->toBe('paid');
    });
});

it('never revives an order cancelled before its payment arrived: it stays cancelled and owes it back', function (): void {
    Event::fake([OrderPaidAfterCancellation::class]);
    $this->store->run(function (): void {
        $order = unpaidOrder();
        $payment = startFakePayment($order);
        $this->travel(61)->minutes();
        app(CancelOrder::class)->expire($order);

        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);
        expect(verifyFakePayment($payment)->status->value)->toBe('succeeded')
            ->and($order->refresh()->only(['refund_due']))->toBe(['refund_due' => true])
            ->and([$order->status->value, $order->payment_status->value])->toBe(['cancelled', 'paid']);
    });

    Event::assertDispatchedTimes(OrderPaidAfterCancellation::class, 1);
});

it('works out the order\'s payment status from what was paid and refunded', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder(10000);
        $record = static fn (int $paid, int $refunded): string => app(RecordOrderPayments::class)->handle($order, Money::ofMinor($paid, 'NGN'), Money::ofMinor($refunded, 'NGN'))->payment_status->value;
        $total = $order->total->minorAmount();

        expect($record(0, 0))->toBe('unpaid')
            ->and($record(500, 0))->toBe('partially_paid')
            ->and($order->refresh()->paid_at)->toBeNull()
            ->and($record($total, 0))->toBe('paid')
            ->and($order->refresh()->paid_at)->not->toBeNull()
            ->and($record($total, 500))->toBe('partially_refunded')
            ->and($record($total, $total))->toBe('refunded');
    });
});

it('looks after payments already under way whatever the plan or the store\'s status, but starts new ones only on the plan', function (string $state): void {
    $payment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
    putFakeGatewayInState($state);

    $this->store->run(function () use ($payment): void {
        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);

        expect(verifyFakePayment($payment)->status->value)->toBe('succeeded')
            ->and(static fn () => startFakePayment(unpaidOrder()))->toThrow(PaymentGatewayNotAvailableException::class);
    });
})->with(['locked', 'disabled', 'suspended']);

it('settles a payment under the order\'s lock first, then the payment\'s, so it waits for anything changing the order', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder();
        $payment = startFakePayment($order);
        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);

        $other = SecondConnection::open()->holdRowLock($order);
        expect($other->blockedStatement(static fn () => verifyFakePayment($payment)))->toContain('"orders"')->toContain('for update');
        $other->rollBack();

        expect(verifyFakePayment($payment)->status->value)->toBe('succeeded');
    });
});

it('keeps gateways with money in motion connected to the account and mode that took it', function (): void {
    $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));

    // Disconnecting meanwhile keeps the keys (GatewayKeysInMotionTest).
    app(Tests\Fixtures\Payments\FakeGatewayScript::class)->accounts['sk_live_otheraccount'] = 'acct_other';
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('live', 'otheraccount')])->assertConflict()->assertJsonPath('code', 'payment_gateway_account_changed');
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('test', 'abcdefghij')])->assertConflict()->assertJsonPath('code', 'payment_gateway_mode_changed');

    // Rotating keys on the same account, and turning it off, are always fine.
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('live', 'rotatedkeys')])->assertOk();
    staffOrderRequest('PATCH', '/payment-gateways/fake_gateway/connection', ['enabled' => false])->assertOk();
});

it('keeps payments as financial records: what they were for never changes, status only moves forward, nothing is deleted', function (): void {
    $this->store->run(function (): void {
        $payment = startFakePayment(unpaidOrder());
        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live, '4242');
        verifyFakePayment($payment);
        $row = static fn (): Illuminate\Database\Query\Builder => DB::table('payments')->where('id', $payment->id);

        expect($payment->refresh()->only(['card_brand', 'card_last4']))->toBe(['card_brand' => 'visa', 'card_last4' => '4242'])
            ->and(static fn () => $row()->update(['amount_amount' => 1]))->toThrow(QueryException::class, 'never change')
            ->and(static fn () => $row()->update(['currency' => 'USD']))->toThrow(QueryException::class, 'never change')
            ->and(static fn () => $row()->update(['is_test' => true]))->toThrow(QueryException::class, 'never change')
            ->and(static fn () => $row()->update(['received_amount' => 1]))->toThrow(QueryException::class, 'recorded once')
            ->and(static fn () => $row()->update(['status' => 'pending', 'succeeded_at' => null]))->toThrow(QueryException::class, "can't go from succeeded to pending")
            ->and(static fn () => $row()->update(['card_last4' => '1111']))->toThrow(QueryException::class, 'card details')
            ->and(static fn () => $row()->update(['card_brand' => null, 'card_last4' => null]))->toThrow(QueryException::class, 'card details')
            ->and(static fn () => $row()->delete())->toThrow(QueryException::class, 'financial records')
            ->and(static fn () => DB::statement('truncate payments cascade'))->toThrow(QueryException::class, 'financial records')
            ->and(static fn () => DB::table('payment_events')->delete())->toThrow(QueryException::class, 'financial records')
            ->and(app(PersonalDataErasure::class)->run(static fn (): int => $row()->update(['card_brand' => null, 'card_last4' => null])))->toBe(1);
    });
});
