<?php

declare(strict_types=1);

use App\Tenant\Payments\Actions\CheckPendingRefund;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Events\PaymentFailed;
use App\Tenant\Payments\Events\PaymentGatewayNeedsReconnecting;
use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b: a gateway that refuses the store's keys (invalid or revoked)
 * never fails a payment or a refund. A payment stays pending; a refund being
 * sent wasn't made, and goes to staff review, never sent again by itself.
 * The connection is marked as needing the merchant to reconnect, who is
 * alerted once; reconnecting, or a check that finds the keys work, clears it.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    // Each payment's and refund's own checks are run by hand.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('leaves a payment being verified pending, never failed, and asks the merchant once to reconnect', function (): void {
    $this->freezeSecond();
    Event::fake([PaymentGatewayNeedsReconnecting::class, PaymentFailed::class]);
    $payment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
    $this->script->refusesKeys = true;

    $this->store->run(static fn () => verifyFakePayment($payment));
    $this->travel(5)->minutes();
    $checked = $this->store->run(static fn (): Payment => verifyFakePayment($payment));

    expect([$checked->status->value, $checked->failure_code, $checked->needs_review_at, $checked->checks])->toBe(['pending', null, null, 2])
        ->and(gatewayKeysRefusedAt())->toBe(now()->subMinutes(5)->toIso8601String());
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 1);
    Event::assertNotDispatched(PaymentFailed::class);
});

it('leaves a payment being started pending, to be checked, and asks the merchant to reconnect', function (): void {
    Event::fake([PaymentGatewayNeedsReconnecting::class]);
    $this->script->refusesKeys = true;
    $order = $this->store->run(static fn () => unpaidOrder());

    expect(fn () => $this->store->run(static fn () => startFakePayment($order)))->toThrow(PaymentGatewayUnreachableException::class)
        ->and($this->store->run(static fn (): array => [Payment::query()->sole()->status->value, Payment::query()->sole()->failure_code]))->toBe(['pending', null])
        ->and(gatewayKeysRefusedAt())->not->toBeNull();
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 1);
});

it('sends a refund the gateway refused for the keys to staff review, never failed and never sent again by itself', function (): void {
    Event::fake([PaymentGatewayNeedsReconnecting::class, RefundFailed::class]);
    [$order] = gatewayPaidOrder();
    $this->script->refusesKeys = true;

    $part = refundOrder($order, 4000)->paymentRefunds->sole();
    $this->script->refusesKeys = false;
    $this->travel(60)->minutes();
    $checked = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$checked->status->value, $checked->failure_code, $checked->review_reason?->value, $this->script->timesCalled('refund')])->toBe(['pending', null, 'credentials_refused', 1])
        ->and(gatewayKeysRefusedAt())->not->toBeNull();
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 1);
    Event::assertNotDispatched(RefundFailed::class);
});

it('sends a refund whose sending went unanswered to staff review when the gateway then refuses the keys, and asks the merchant to reconnect', function (): void {
    Event::fake([PaymentGatewayNeedsReconnecting::class]);
    [$order] = gatewayPaidOrder();
    $this->script->unavailable = true;
    $part = refundOrder($order, 4000)->paymentRefunds->sole();
    $this->script->unavailable = false;
    $this->script->refusesKeys = true;

    $checked = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$checked->status->value, $checked->review_reason?->value, $this->script->timesCalled('refund')])->toBe(['pending', 'lookup_failed', 1])
        ->and(gatewayKeysRefusedAt())->not->toBeNull();
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 1);
});

it('keeps asking about a refund the gateway acknowledged while it refuses the keys', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending, 'fake_r');
    $part = refundOrder($order, 4000)->paymentRefunds->sole();
    $this->script->refusesKeys = true;

    $checked = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$checked->status->value, $checked->needs_review_at])->toBe(['pending', null])
        ->and(gatewayKeysRefusedAt())->not->toBeNull();
});

it('shows the merchant that the gateway needs reconnecting, and clears it once a check finds the keys work, or on reconnecting', function (): void {
    $this->freezeSecond();
    Event::fake([PaymentGatewayNeedsReconnecting::class]);
    $this->script->refusesKeys = true;

    staffOrderRequest('POST', '/payment-gateways/fake_gateway/connection/checks')->assertOk()->assertJsonPath('data.valid', false);
    $shown = collect(staffOrderRequest('GET', '/payment-gateways')->json('data'))->firstWhere('key', 'fake_gateway');

    expect($shown['connection']['needs_attention_at'])->toBe(now()->toIso8601String());
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 1);

    $this->script->refusesKeys = false;
    staffOrderRequest('POST', '/payment-gateways/fake_gateway/connection/checks')->assertOk()->assertJsonPath('data.valid', true);
    expect(gatewayKeysRefusedAt())->toBeNull();

    $this->script->refusesKeys = true;
    staffOrderRequest('POST', '/payment-gateways/fake_gateway/connection/checks')->assertOk();
    $this->script->refusesKeys = false;
    connectFakeGateway('live', 'abcdefghij');

    expect(gatewayKeysRefusedAt())->toBeNull();
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 2);
});
