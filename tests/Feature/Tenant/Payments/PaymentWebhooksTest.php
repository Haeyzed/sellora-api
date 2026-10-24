<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Tenant\Payments\Actions\HandlePaymentWebhook;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Events\PaymentDisputed;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Jobs\ProcessPaymentWebhook;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentEvent;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15.3: payment webhooks. A webhook finds its store by the random
 * endpoint ID in its URL; the store checks the signature with its own keys
 * before anything in it is parsed, stored or acted on; it is recorded once
 * per gateway event, encrypted, and only ever says "go and ask the gateway".
 * Webhooks keep working whatever the plan or the store's suspension.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class]);
    $this->payment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
});

afterEach(function (): void {
    deleteAllStores();
});

it('asks the gateway about the payment a signed webhook names, and records the webhook once, encrypted', function (): void {
    gatewayAnswers($this->payment, GatewayPaymentState::Succeeded, $this->payment->amount, GatewayMode::Live);

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'payment', 'reference' => $this->payment->reference])->assertOk()->assertExactJson(['received' => true]);

    $this->store->run(function (): void {
        $event = PaymentWebhookEvent::query()->sole();

        expect($this->payment->refresh()->status->value)->toBe('succeeded')
            ->and($this->payment->order->payment_status->value)->toBe('paid')
            ->and([$event->event_id, $event->kind->value, $event->reference])->toBe(['evt_1', 'payment', $this->payment->reference])
            ->and($event->processed_at)->not->toBeNull()
            ->and($event->payload)->toContain('evt_1')
            ->and(DB::table('payment_webhook_events')->value('payload'))->not->toContain('evt_1');
    });
});

it('never trusts what a webhook says about the payment: only the gateway, asked server-side, settles it', function (): void {
    sendFakeWebhook(['id' => 'evt_1', 'type' => 'payment', 'reference' => $this->payment->reference, 'status' => 'success', 'amount' => $this->payment->amount->minorAmount()])->assertOk();

    expect($this->script->timesCalled('verifyPayment', $this->payment->reference))->toBe(1)
        ->and($this->store->run(fn (): string => $this->payment->refresh()->status->value))->toBe('pending');
});

it('refuses a webhook not signed with the store\'s own keys, before reading or keeping anything in it', function (?string $secret, string $body): void {
    $headers = $secret === null ? [] : ['HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $body, $secret)];

    $this->call('POST', centralUrl('/webhooks/payments/'.fakeWebhookEndpoint()), server: ['CONTENT_TYPE' => 'application/json', ...$headers], content: $body)
        ->assertUnauthorized()
        ->assertJsonPath('code', 'webhook_signature_invalid');

    expect($this->script->timesCalled('verifyPayment'))->toBe(0)
        ->and($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(0);
})->with([
    'another account\'s keys' => ['sk_live_someoneelse', '{"id":"evt_1","type":"payment"}'],
    'the test keys' => ['sk_test_0123456789', '{"id":"evt_1","type":"payment"}'],
    'no signature' => [null, '{"id":"evt_1","type":"payment"}'],
    'a body that isn\'t JSON' => ['sk_live_someoneelse', 'not json at all'],
]);

it('handles a resent event once', function (): void {
    // The payment stays pending, so every time it is handled would ask the gateway again.
    Queue::fake([CheckPaymentWithGateway::class, ProcessPaymentWebhook::class]);
    $webhook = ['id' => 'evt_1', 'type' => 'payment', 'reference' => $this->payment->reference];

    sendFakeWebhook($webhook)->assertOk();
    sendFakeWebhook($webhook)->assertOk();
    Queue::assertPushedTimes(ProcessPaymentWebhook::class, 1);

    // Handled twice, as a retried job or the sweep would: the second time it is already done, so left alone.
    $job = Queue::pushed(ProcessPaymentWebhook::class)->sole();
    $this->store->run(static fn () => $job->handle(app(HandlePaymentWebhook::class)));
    $this->store->run(static fn () => $job->handle(app(HandlePaymentWebhook::class)));

    expect($this->script->timesCalled('verifyPayment', $this->payment->reference))->toBe(1)
        ->and($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(1);
});

it('answers 404 for an endpoint that leads nowhere, and slows down an address that keeps trying, but never for real endpoints', function (): void {
    config(['api.rate_limits.unknown_webhook_endpoints' => 2]);
    $orphan = app(WebhookEndpoints::class)->create((string) $this->store->getTenantKey(), 'fake_gateway');

    sendFakeWebhook(['id' => 'evt_1'], Str::lower(Str::random(40)))->assertNotFound();
    sendFakeWebhook(['id' => 'evt_1'], $orphan)->assertNotFound();
    sendFakeWebhook(['id' => 'evt_1'], Str::lower(Str::random(40)))->assertTooManyRequests();
    sendFakeWebhook(['id' => 'evt_2', 'type' => 'payment', 'reference' => $this->payment->reference])->assertOk();
});

it('limits each store\'s webhooks per minute, keeping nothing over the limit', function (): void {
    config(['api.rate_limits.tenant_webhooks' => 2]);

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'ignored'])->assertOk();
    sendFakeWebhook(['id' => 'evt_2', 'type' => 'ignored'])->assertOk();
    sendFakeWebhook(['id' => 'evt_3', 'type' => 'ignored'])->assertTooManyRequests();

    expect($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(2);
});

it('limits each endpoint\'s webhooks per minute too, so one flooded endpoint leaves the store\'s others working', function (): void {
    config(['api.rate_limits.tenant_webhook_endpoints' => 2]);
    connectFakeGateway('live', gateway: 'local_fake_gateway');

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'ignored'])->assertOk();
    sendFakeWebhook(['id' => 'evt_2', 'type' => 'ignored'])->assertOk();
    sendFakeWebhook(['id' => 'evt_3', 'type' => 'ignored'])->assertTooManyRequests();
    sendFakeWebhook(['id' => 'evt_4', 'type' => 'ignored'], fakeWebhookEndpoint('local_fake_gateway'))->assertOk();

    expect($this->store->run(static fn (): array => PaymentWebhookEvent::query()->orderBy('id')->pluck('event_id')->all()))->toBe(['evt_1', 'evt_2', 'evt_4']);
});

it('refuses a webhook to the endpoint of the mode the gateway is not connected in, before reading it', function (): void {
    // Connected live: the test endpoint's webhooks are signed with test keys the store doesn't hold.
    sendFakeWebhook(['id' => 'evt_1', 'type' => 'payment', 'reference' => $this->payment->reference], fakeWebhookEndpoint(mode: GatewayMode::Test))->assertNotFound();
    sendFakeWebhook(['id' => 'evt_2', 'type' => 'payment', 'reference' => $this->payment->reference], fakeWebhookEndpoint(mode: GatewayMode::Test), fakeGatewayCredentials('test')['secret_key'])->assertNotFound();

    expect($this->script->timesCalled('verifyPayment'))->toBe(0)
        ->and($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(0);

    // A gateway connected with test keys takes its webhooks at its test endpoint, and none at its live one.
    connectFakeGateway('test', gateway: 'local_fake_gateway');
    sendFakeWebhook(['id' => 'evt_3', 'type' => 'ignored'], fakeWebhookEndpoint('local_fake_gateway', GatewayMode::Test), fakeGatewayCredentials('test')['secret_key'])->assertOk();
    sendFakeWebhook(['id' => 'evt_4', 'type' => 'ignored'], fakeWebhookEndpoint('local_fake_gateway', GatewayMode::Live), fakeGatewayCredentials('test')['secret_key'])->assertNotFound();
});

it('keeps handling webhooks for payments under way whatever the plan, and while the store is suspended', function (string $state): void {
    $state === 'store suspended' ? $this->store->update(['status' => TenantStatus::Suspended]) : putFakeGatewayInState($state);
    gatewayAnswers($this->payment, GatewayPaymentState::Succeeded, $this->payment->amount, GatewayMode::Live);

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'payment', 'reference' => $this->payment->reference])->assertOk();

    expect($this->store->run(fn (): string => $this->payment->order->refresh()->payment_status->value))->toBe('paid');
})->with(['locked', 'disabled', 'suspended', 'store suspended']);

it('sends a closed store no webhooks', function (): void {
    $endpoint = fakeWebhookEndpoint();
    closeStoreForPurging($this->store, now()->addDays(90));

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'payment', 'reference' => $this->payment->reference], $endpoint)->assertNotFound();
});

it('records a dispute on its payment and tells staff, without changing any money', function (): void {
    Event::fake([PaymentDisputed::class]);

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'dispute', 'reference' => $this->payment->reference])->assertOk();

    $this->store->run(function (): void {
        expect($this->payment->refresh()->status->value)->toBe('pending')
            ->and($this->payment->order->payment_status->value)->toBe('unpaid')
            ->and($this->payment->events()->get()->map(static fn (PaymentEvent $event): string => $event->type->value)->all())->toBe(['started', 'disputed']);
    });
    Event::assertDispatched(PaymentDisputed::class, fn (PaymentDisputed $event): bool => $event->gateway === 'fake_gateway' && $event->payment?->is($this->payment) === true);
    expect($this->script->timesCalled('verifyPayment'))->toBe(0);
});

it('does nothing for a webhook about no payment of ours, or one taken through another connection', function (): void {
    connectFakeGateway('live', gateway: 'local_fake_gateway');
    $other = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder(), gateway: 'local_fake_gateway'));

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'payment', 'reference' => 'pay_someone_elses'])->assertOk();
    sendFakeWebhook(['id' => 'evt_2', 'type' => 'payment', 'reference' => $other->reference])->assertOk();
    sendFakeWebhook(['id' => 'evt_3', 'type' => 'refund', 'reference' => $this->payment->reference])->assertOk();

    expect($this->script->timesCalled('verifyPayment'))->toBe(0)
        ->and($this->store->run(static fn (): int => PaymentWebhookEvent::query()->whereNotNull('processed_at')->count()))->toBe(3);
});

it('asks about each refund of a payment when a refund webhook names only the payment, a failed one included, never one in review or gone through', function (): void {
    // Each refund's own checks are left out, so only the webhook asks.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
    [$order, $paid] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending, 'fake_r');
    $pending = refundOrder($order, 1000)->paymentRefunds->sole();
    $reviewed = refundOrder($order, 1000)->paymentRefunds->sole();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Failed, 'fake_f', null, 'declined');
    $failed = refundOrder($order, 1000)->paymentRefunds->sole();
    $this->script->refundAnswer = null;
    $settled = refundOrder($order, 1000)->paymentRefunds->sole();
    $this->store->run(static fn () => DB::table('payment_refunds')->where('id', $reviewed->id)->update(['needs_review_at' => now(), 'review_reason' => 'gateway_unclear']));
    $asked = fn (): array => array_map(fn (PaymentRefund $part): int => $this->script->timesCalled('verifyRefund', $part->reference), [$pending, $failed, $reviewed, $settled]);
    $before = $asked();

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'refund', 'reference' => $paid->reference])->assertOk();

    expect($failed->status->value)->toBe('failed')
        ->and(array_map(static fn (int $after, int $earlier): int => $after - $earlier, $asked(), $before))->toBe([1, 1, 0, 0]);
});

it('empties what webhooks said after their retention period, and forgets the events later still', function (): void {
    sendFakeWebhook(['id' => 'evt_1', 'type' => 'ignored'])->assertOk();

    $this->travel(31)->days();
    $this->artisan('retention:purge')->assertSuccessful();
    expect($this->store->run(static fn (): array => PaymentWebhookEvent::query()->sole()->only(['event_id', 'payload'])))->toBe(['event_id' => 'evt_1', 'payload' => null]);

    // Still recognised as a resend while it is kept.
    sendFakeWebhook(['id' => 'evt_1', 'type' => 'ignored'])->assertOk();
    expect($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(1);

    $this->travel(150)->days();
    $this->artisan('retention:purge')->assertSuccessful();
    expect($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(0);
});
