<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Features;
use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\ConnectPaymentGateway;
use App\Tenant\Payments\Actions\StartPayment;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Data\GatewayPaymentResult;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakeGatewayScript;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Fixtures\Payments\FakePaymentGatewayServiceProvider;
use Tests\Fixtures\Payments\LocalFakePaymentGateway;
use Tests\Fixtures\Payments\LocalFakePaymentGatewayServiceProvider;

/*
|--------------------------------------------------------------------------
| For Tests\Feature\Tenant\Payments
|--------------------------------------------------------------------------
*/

/**
 * Registers the fake payment gateway as an integration and puts it on the test store's plan, or in another state; returns its script.
 *
 * @param  string  $state  "enabled", "locked" (removed by a downgrade), "disabled" (switched off by the merchant), "suspended" (the whole store) or "unavailable".
 */
function useFakeGateway(string $state = 'enabled'): FakeGatewayScript
{
    app()->register(FakePaymentGatewayServiceProvider::class);
    app()->register(LocalFakePaymentGatewayServiceProvider::class);
    putFakeGatewayInState($state);

    return app(FakeGatewayScript::class);
}

/**
 * Moves the fake gateway on the test store's plan to another state, as a downgrade, a merchant's switch or a suspension would. The local fake gateway stays on the plan, unless the whole store is suspended.
 */
function putFakeGatewayInState(string $state): void
{
    $key = FakePaymentGateway::KEY;
    $local = LocalFakePaymentGateway::KEY;
    $snapshot = match ($state) {
        'enabled' => FakeFeatureSource::snapshot(entitled: [$key, $local]),
        'locked' => FakeFeatureSource::snapshot(entitled: [$local], removed: [$key]),
        'disabled' => FakeFeatureSource::snapshot(entitled: [$key, $local], merchantDisabled: [$key]),
        'suspended' => FakeFeatureSource::snapshot(entitled: [$key, $local], isStoreSuspended: true),
        default => FakeFeatureSource::snapshot(entitled: [$local]),
    };
    $storeId = (string) test()->store->getTenantKey();

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, $snapshot));
    app(Features::class)->forget($storeId);
}

/**
 * The fake gateway's credentials in a mode, for an account.
 *
 * @return array{secret_key: string, public_key: string}
 */
function fakeGatewayCredentials(string $mode = 'test', string $suffix = '0123456789'): array
{
    return ['secret_key' => "sk_{$mode}_{$suffix}", 'public_key' => "pk_{$mode}_{$suffix}"];
}

/**
 * Connects the fake gateway to the test store, as its owner, in a mode.
 */
function connectFakeGateway(string $mode = 'test', string $suffix = '0123456789', string $gateway = FakePaymentGateway::KEY): PaymentGatewayConnection
{
    return test()->store->run(static fn (): PaymentGatewayConnection => app(ConnectPaymentGateway::class)->handle(test()->owner, $gateway, fakeGatewayCredentials($mode, $suffix)));
}

/**
 * A live order of one shipped line, placed in the test store, unpaid.
 */
function unpaidOrder(int $price = 10000, ?string $currency = null, bool $isTest = false): Order
{
    return placeOrder(orderDraft([[cartVariant($price, stock: 5), 1]], orderShippingMethod(), currency: $currency, isTest: $isTest));
}

/**
 * Starts a payment of what is left on the order with the fake gateway, as Checkout will.
 */
function startFakePayment(Order $order, ?string $idempotencyKey = null, string $gateway = FakePaymentGateway::KEY): Payment
{
    return app(StartPayment::class)->handle($order, $gateway, 'https://shop.example.com/orders/return', $idempotencyKey);
}

/**
 * Makes the fake gateway answer for a payment when it is next asked.
 */
function gatewayAnswers(Payment $payment, GatewayPaymentState $state, ?Money $amount = null, ?GatewayMode $mode = null, ?string $cardLast4 = null): void
{
    app(FakeGatewayScript::class)->payments[$payment->reference] = new GatewayPaymentResult($state, $amount, $mode, 'fake_'.$payment->reference, $state === GatewayPaymentState::Failed ? 'declined' : null, $cardLast4 === null ? null : 'visa', $cardLast4);
}

/**
 * Asks the fake gateway about a payment, as a webhook or the buyer coming back would, and returns it freshly read.
 */
function verifyFakePayment(Payment $payment): Payment
{
    return app(VerifyPayment::class)->handle($payment->refresh())->refresh();
}

/**
 * The checks with the gateway queued (on a faked queue) for a payment, by their place in its own schedule; null for a one-off check by the sweep.
 *
 * @return list<int|null>
 */
function queuedChecks(Payment $payment): array
{
    return array_values(Queue::pushed(CheckPaymentWithGateway::class, static fn (CheckPaymentWithGateway $job): bool => $job->paymentId === $payment->id)
        ->map(static fn (CheckPaymentWithGateway $job): ?int => $job->checkNumber)
        ->all());
}

/**
 * The webhook endpoint ID of the test store's connection with a fake gateway.
 */
function fakeWebhookEndpoint(string $gateway = FakePaymentGateway::KEY): string
{
    return (string) test()->store->run(static fn (): ?string => PaymentGatewayConnection::query()->where('gateway', $gateway)->value('endpoint_id'));
}

/**
 * Sends a webhook from the fake gateway to an endpoint on the central domain, signed with a secret key (the live test keys by default), as the gateway would.
 *
 * @param  array<string, mixed>  $payload
 */
function sendFakeWebhook(array $payload, ?string $endpointId = null, ?string $secret = null): TestResponse
{
    $body = (string) json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret ?? fakeGatewayCredentials('live')['secret_key']);

    return test()->call('POST', centralUrl('/webhooks/payments/'.($endpointId ?? fakeWebhookEndpoint())), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => $signature], content: $body);
}
