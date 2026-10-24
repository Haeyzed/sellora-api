<?php

declare(strict_types=1);

use App\Landlord\Integrations\Models\WebhookEndpoint;
use App\Shared\Money\Money;
use App\Tenant\Payments\Actions\CheckPendingRefund;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Jobs\ProcessPaymentWebhook;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\PaymentGatewayResolver;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b.1: money already in motion keeps working (section 3.3). A
 * gateway disconnected while payments or refunds it took are still in
 * motion keeps its keys and webhook endpoints, and keys replaced meanwhile
 * are kept as retired ones, until all of it has settled; the hourly sweep
 * then forgets them. Nothing new is ever started with kept keys.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class, ProcessPaymentWebhook::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('keeps a disconnected gateway\'s keys and endpoints while a payment it took is pending, still settles it, then forgets them', function (): void {
    $payment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
    $endpoint = fakeWebhookEndpoint();

    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertOk()
        ->assertJsonPath('data.status', 'disconnected')
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.webhook_urls.live', centralUrl('/webhooks/payments/'.$endpoint));

    expect(gatewayKeysHeld())->toBe(['credentials' => fakeGatewayCredentials('live'), 'retired_credentials' => null, 'endpoints' => 2])
        ->and(WebhookEndpoint::query()->count())->toBe(2)
        // Never offered for anything new.
        ->and($this->store->run(static fn (): array => app(PaymentGatewayResolver::class)->forNewPayment('NGN', 'NG', false)))->toBe([]);

    // Its webhooks still arrive, and the sweep keeps everything while the payment is pending.
    sendFakeWebhook(['id' => 'evt_1', 'type' => 'payment', 'reference' => $payment->reference], $endpoint)->assertOk();
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(gatewayKeysHeld()['credentials'])->toBe(fakeGatewayCredentials('live'))
        ->and(WebhookEndpoint::query()->count())->toBe(2);

    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);
    expect($this->store->run(static fn (): string => verifyFakePayment($payment)->status->value))->toBe('succeeded');

    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(gatewayKeysHeld())->toBe(['credentials' => null, 'retired_credentials' => null, 'endpoints' => 0])
        ->and(WebhookEndpoint::query()->count())->toBe(0);
    sendFakeWebhook(['id' => 'evt_2', 'type' => 'payment', 'reference' => $payment->reference], $endpoint)->assertNotFound();
});

it('keeps a disconnected gateway\'s keys while a refund it sent is on its way back, then forgets them', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    $part = refundOrder($order, 4000)->paymentRefunds->sole();

    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertOk()->assertJsonPath('data.status', 'disconnected');
    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(gatewayKeysHeld()['credentials'])->toBe(fakeGatewayCredentials('live'));

    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r', Money::ofMinor(4000, 'NGN'));
    expect($this->store->run(static fn (): string => app(CheckPendingRefund::class)->handle($part->refresh())->status->value))->toBe('succeeded');

    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(gatewayKeysHeld())->toBe(['credentials' => null, 'retired_credentials' => null, 'endpoints' => 0]);
});

it('still sends a refund that never reached the gateway through one disconnected meanwhile', function (): void {
    [$order] = gatewayPaidOrder();
    $this->script->unavailable = true;
    $part = refundOrder($order, 4000)->paymentRefunds->sole();
    $this->script->unavailable = false;

    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertOk();
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    $this->travel(config()->integer('payments.checks.refund_resend_after_minutes'))->minutes();

    expect($this->store->run(static fn (): string => app(CheckPendingRefund::class)->handle($part->refresh())->status->value))->toBe('succeeded')
        ->and($this->script->timesCalled('refund', $part->reference))->toBe(2);
});

it('keeps keys replaced while a payment is pending as retired ones that still check webhooks, until it settles', function (): void {
    $payment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
    $old = fakeGatewayCredentials('live');

    connectFakeGateway('live', 'rotatedkeys');
    expect(gatewayKeysHeld())->toBe(['credentials' => fakeGatewayCredentials('live', 'rotatedkeys'), 'retired_credentials' => [$old], 'endpoints' => 2]);

    // Replacing them again keeps every set that took money still in motion, each once.
    connectFakeGateway('live', 'rotatedagain');
    connectFakeGateway('live', 'rotatedagain');
    expect(gatewayKeysHeld()['retired_credentials'])->toBe([$old, fakeGatewayCredentials('live', 'rotatedkeys')]);

    sendFakeWebhook(['id' => 'evt_1', 'type' => 'ignored'], secret: $old['secret_key'])->assertOk();
    sendFakeWebhook(['id' => 'evt_2', 'type' => 'ignored'], secret: fakeGatewayCredentials('live', 'rotatedagain')['secret_key'])->assertOk();

    $this->artisan('payments:check-pending')->assertSuccessful();
    expect(gatewayKeysHeld()['retired_credentials'])->toHaveCount(2);

    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);
    $this->store->run(static fn (): Payment => verifyFakePayment($payment));
    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(gatewayKeysHeld())->toBe(['credentials' => fakeGatewayCredentials('live', 'rotatedagain'), 'retired_credentials' => null, 'endpoints' => 2]);
    sendFakeWebhook(['id' => 'evt_3', 'type' => 'ignored'], secret: $old['secret_key'])->assertUnauthorized();
});

it('holds a gateway reconnected while money is still in motion to the account and mode that took it', function (): void {
    $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertOk();
    $this->script->accounts['sk_live_otheraccount'] = 'acct_other';

    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('test', 'abcdefghij')])->assertConflict()->assertJsonPath('code', 'payment_gateway_mode_changed');
    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('live', 'otheraccount')])->assertConflict()->assertJsonPath('code', 'payment_gateway_account_changed');
    expect(gatewayKeysHeld()['credentials'])->toBe(fakeGatewayCredentials('live'));

    staffOrderRequest('PUT', '/payment-gateways/fake_gateway/connection', ['credentials' => fakeGatewayCredentials('live', 'rotatedkeys')])->assertOk()->assertJsonPath('data.status', 'connected');
    expect(gatewayKeysHeld())->toBe(['credentials' => fakeGatewayCredentials('live', 'rotatedkeys'), 'retired_credentials' => [fakeGatewayCredentials('live')], 'endpoints' => 2]);
});

it('forgets a disconnected gateway\'s keys at once when nothing is in motion', function (): void {
    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertOk()->assertJsonPath('data.webhook_urls', ['test' => null, 'live' => null]);

    expect(gatewayKeysHeld())->toBe(['credentials' => null, 'retired_credentials' => null, 'endpoints' => 0])
        ->and(WebhookEndpoint::query()->count())->toBe(0);
});
