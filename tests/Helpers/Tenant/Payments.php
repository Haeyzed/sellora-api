<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Features;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Payments\Actions\ChangeManualPaymentMethod;
use App\Tenant\Payments\Actions\ConnectPaymentGateway;
use App\Tenant\Payments\Actions\RecordManualPayment;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Actions\StartPayment;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Data\GatewayPaymentResult;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentPermission;
use App\Tenant\Payments\Enums\RefundPurpose;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentEvent;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Models\Refund;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
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
 * A live order of one shipped line, placed in the test store, unpaid; paid on delivery, or with a later deadline, when asked.
 */
function unpaidOrder(int $price = 10000, ?string $currency = null, bool $isTest = false, bool $payOnDelivery = false, ?CarbonImmutable $paymentDueAt = null): Order
{
    return placeOrder(orderDraft([[cartVariant($price, stock: 5), 1]], orderShippingMethod(), currency: $currency, paymentDueAt: $paymentDueAt, isTest: $isTest, payOnDelivery: $payOnDelivery));
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
 * The webhook endpoint ID of the test store's connection with a fake gateway, for the mode it is in, or another.
 */
function fakeWebhookEndpoint(string $gateway = FakePaymentGateway::KEY, ?GatewayMode $mode = null): string
{
    return (string) test()->store->run(static function () use ($gateway, $mode): ?string {
        $connection = PaymentGatewayConnection::query()->where('gateway', $gateway)->sole();

        return $connection->endpointFor($mode ?? $connection->mode ?? GatewayMode::Test);
    });
}

/**
 * What the test store's connection with a fake gateway still holds: its keys, the keys it kept from before they were replaced, and how many webhook endpoints.
 *
 * @return array{credentials: array<string, string>|null, retired_credentials: list<array<string, string>>|null, endpoints: int}
 */
function gatewayKeysHeld(string $gateway = FakePaymentGateway::KEY): array
{
    return test()->store->run(static function () use ($gateway): array {
        $connection = PaymentGatewayConnection::query()->where('gateway', $gateway)->sole();

        return ['credentials' => $connection->credentials, 'retired_credentials' => $connection->retired_credentials, 'endpoints' => count($connection->endpointIds())];
    });
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

/**
 * A payment's history, oldest first, by event type.
 *
 * @return list<string>
 */
function paymentHistory(Payment $payment): array
{
    return array_values($payment->events()->get()->map(static fn (PaymentEvent $event): string => $event->type->value)->all());
}

/**
 * Turns on a payment method paid by hand in the test store, as its owner, with any settings given.
 *
 * @param  array{enabled?: bool, deadline_hours?: int, collection_alert_hours?: int, instructions?: string|null}  $settings
 */
function offerManualMethod(PaymentMethod $method, array $settings = []): void
{
    test()->store->run(static fn () => app(ChangeManualPaymentMethod::class)->handle(test()->owner, $method, ['enabled' => true, ...$settings]));
}

/**
 * An order in the test store expecting payment by a method paid by hand (turned on first), and its pending payment.
 *
 * @return array{Order, Payment}
 */
function manuallyPaidOrder(PaymentMethod $method, int $price = 10000): array
{
    offerManualMethod($method);

    return test()->store->run(static function () use ($method, $price): array {
        $order = unpaidOrder($price, payOnDelivery: $method === PaymentMethod::CashOnDelivery, paymentDueAt: CarbonImmutable::now()->addHours(72));

        return [$order, app(StartManualPayment::class)->handle($order, $method)];
    });
}

/**
 * Staff (the owner) recording money received for a payment paid by hand, in minor units of its currency.
 */
function recordReceived(Payment $payment, int $minorAmount, ?string $reference = null): Payment
{
    return test()->store->run(static fn (): Payment => app(RecordManualPayment::class)->handle(test()->owner, $payment->refresh(), Money::ofMinor($minorAmount, $payment->currency), $reference)->refresh());
}

/**
 * An order in the test store paid in full through the fake gateway (connected live): one line of ₦100 unless others are given, shipped for ₦15 when they are. Returns it and its payment, freshly read.
 *
 * @param  list<array{0: ProductVariant, 1: int}>|null  $lines  Each variant and how many.
 * @return array{Order, Payment}
 */
function gatewayPaidOrder(?array $lines = null): array
{
    return test()->store->run(static function () use ($lines): array {
        $order = $lines === null ? unpaidOrder(10000) : placeOrder(orderDraft($lines, orderShippingMethod()));
        $payment = startFakePayment($order);
        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $order->total, GatewayMode::Live);

        return [$order->refresh(), verifyFakePayment($payment)];
    });
}

/**
 * Staff (the owner unless another is given) refunding an order: an amount in minor units of its currency, or lines (units by line public ID) and shipping; from one payment when given, and with a purpose when given.
 *
 * @param  array<string, int>  $lines
 */
function refundOrder(Order $order, ?int $amount = null, array $lines = [], bool $shipping = false, ?Payment $from = null, ?string $idempotencyKey = null, ?StaffMember $as = null, ?RefundPurpose $purpose = null): Refund
{
    $request = new RefundRequest($amount === null ? null : Money::ofMinor($amount, $order->currency), $lines, $shipping, $from?->public_id, 'Changed their mind', $idempotencyKey, $purpose);

    return test()->store->run(static fn (): Refund => app(RefundOrder::class)->handle($as ?? test()->owner, $order, $request));
}

/**
 * A staff member in the test store holding only the payment permissions given.
 *
 * @param  list<PaymentPermission>  $permissions
 */
function paymentsStaff(array $permissions): StaffMember
{
    return test()->store->run(static function () use ($permissions): StaffMember {
        $role = Role::findOrCreate('Payments '.implode(' ', array_map(static fn (PaymentPermission $permission): string => $permission->value, $permissions)), StaffMember::GUARD);
        $role->syncPermissions(array_map(static fn (PaymentPermission $permission): Permission => Permission::findByName($permission->value, StaffMember::GUARD), $permissions));
        $staff = StaffMember::factory()->create();
        $staff->assignRole($role);

        return $staff;
    });
}

/**
 * Ships all of an order's line in one shipment as the owner, and delivers it when asked; returns the shipment.
 */
function shipOrder(Order $order, bool $deliver = false): Shipment
{
    $staff = AccountReference::to(test()->owner);
    $shipment = app(MoveShipment::class)->ship(app(CreateShipment::class)->handle($order, [$order->lines()->firstOrFail()->public_id => 1], $staff, 'TRK'), $staff);

    return $deliver ? app(MoveShipment::class)->deliver($shipment, $staff) : $shipment;
}
