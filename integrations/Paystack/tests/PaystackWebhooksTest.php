<?php

declare(strict_types=1);

use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Integrations\Paystack\PaystackGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b.4: Paystack's webhooks. Each is signed with HMAC-SHA512 of its raw
 * body under the secret key of the mode it is for, in "x-paystack-signature";
 * the store checks it (hash_equals) before anything in it is parsed or kept.
 * A webhook only says "go and ask": charge.success has the payment verified
 * with Paystack, and a refund event has the payment's refunds in motion
 * checked. Unknown events and fields are recorded and left alone.
 *
 * No Paystack webhook has been recorded yet: these bodies are put together
 * from Paystack's documentation, so the fields read (event, data.reference,
 * data.transaction_reference) are unconfirmed until one is.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    usePaystack();
    connectPaystack();
    // Each payment's and refund's own checks are run by hand where they matter; webhooks are handled as they arrive.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('verifies the payment a signed charge.success names with Paystack, and records the webhook once', function (): void {
    $payment = $this->store->run(static fn (): Payment => startedPaystackPayment());
    $total = $payment->amount->minorAmount();
    Http::fake(["paystack.test/transaction/verify/{$payment->reference}" => Http::response(paystackFixture('verify-success-003', ['data.reference' => $payment->reference, 'data.amount' => $total, 'data.requested_amount' => $total]))]);
    $webhook = ['event' => 'charge.success', 'data' => ['id' => 6688258824, 'domain' => 'test', 'status' => 'success', 'reference' => $payment->reference, 'amount' => $total, 'currency' => 'NGN']];

    sendPaystackWebhook($webhook)->assertOk();
    sendPaystackWebhook($webhook)->assertOk();

    $this->store->run(static function () use ($payment): void {
        $event = PaymentWebhookEvent::query()->sole();

        expect($payment->refresh()->status->value)->toBe('succeeded')
            ->and([$event->kind->value, $event->reference, $event->processed_at !== null])->toBe(['payment', $payment->reference, true])
            ->and($event->event_id)->toStartWith('body:');
    });
    expect(collect(paystackRequests())->filter(static fn (array $request): bool => str_contains($request[1], '/transaction/verify/'))->count())->toBe(1);
});

it('never trusts what a webhook says: only Paystack, asked, settles a payment', function (): void {
    $payment = $this->store->run(static fn (): Payment => startedPaystackPayment());
    $total = $payment->amount->minorAmount();
    Http::fake(["paystack.test/transaction/verify/{$payment->reference}" => Http::response(paystackFixture('verify-abandoned-001', ['data.reference' => $payment->reference, 'data.amount' => $total, 'data.requested_amount' => $total]))]);

    sendPaystackWebhook(['event' => 'charge.success', 'data' => ['status' => 'success', 'reference' => $payment->reference, 'amount' => $total]])->assertOk();

    expect($this->store->run(static fn (): string => $payment->refresh()->status->value))->toBe('pending');
});

it('refuses a webhook not signed with the key of the mode it came to, before reading or keeping anything in it', function (bool $isJson, Closure $sign): void {
    $payment = $this->store->run(static fn (): Payment => startedPaystackPayment());
    $before = count(paystackRequests());
    $body = $isJson ? (string) json_encode(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]]) : 'not json at all';

    sendPaystackWebhook($body, signature: $sign($body))->assertUnauthorized()->assertJsonPath('code', 'webhook_signature_invalid');

    expect(count(paystackRequests()))->toBe($before)
        ->and($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(0);
})->with([
    'the live key' => [true, static fn (string $body): string => hash_hmac('sha512', $body, PAYSTACK_FAKE_LIVE_KEY)],
    'SHA-256 instead of SHA-512' => [true, static fn (string $body): string => hash_hmac('sha256', $body, PAYSTACK_FAKE_TEST_KEY)],
    'no signature' => [true, static fn (): string => ''],
    'the body changed after signing' => [true, static fn (string $body): string => hash_hmac('sha512', $body.' ', PAYSTACK_FAKE_TEST_KEY)],
    'the body unkeyed' => [true, static fn (string $body): string => hash('sha512', $body)],
    'a body that isn\'t JSON, under the live key' => [false, static fn (string $body): string => hash_hmac('sha512', $body, PAYSTACK_FAKE_LIVE_KEY)],
]);

it('refuses a webhook to the endpoint of the mode the store is not connected in, unread', function (): void {
    $payment = $this->store->run(static fn (): Payment => startedPaystackPayment());
    $before = count(paystackRequests());

    sendPaystackWebhook(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]], endpointId: fakeWebhookEndpoint(PaystackGateway::KEY, GatewayMode::Live))->assertNotFound();
    sendPaystackWebhook(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]], PAYSTACK_FAKE_LIVE_KEY, fakeWebhookEndpoint(PaystackGateway::KEY, GatewayMode::Live))->assertNotFound();

    expect(count(paystackRequests()))->toBe($before)
        ->and($this->store->run(static fn (): int => PaymentWebhookEvent::query()->count()))->toBe(0);
});

it('records and leaves alone a signed webhook it doesn\'t act on, whatever fields it has', function (array|string $webhook): void {
    $before = count(paystackRequests());

    sendPaystackWebhook($webhook)->assertOk();

    expect(count(paystackRequests()))->toBe($before)
        ->and($this->store->run(static fn (): array => PaymentWebhookEvent::query()->sole()->only(['reference'])))->toBe(['reference' => null]);
})->with([
    'an event never acted on' => [['event' => 'subscription.create', 'data' => ['reference' => 'pay_x']]],
    'fields never seen' => [['event' => 'transfer.success', 'something_new' => ['a' => 1], 'data' => ['id' => 1]]],
    'no event' => [['data' => ['reference' => 'pay_x']]],
    'an event that isn\'t text' => [['event' => ['charge.success'], 'data' => ['reference' => 'pay_x']]],
    'no data' => [['event' => 'charge.success']],
    'data that isn\'t an object' => [['event' => 'charge.success', 'data' => 'pay_x']],
    'a reference that isn\'t text' => [['event' => 'charge.success', 'data' => ['reference' => 12345]]],
    'a body that isn\'t JSON' => ['not json at all'],
    'JSON that isn\'t an object' => ['"charge.success"'],
]);

it('checks the refunds in motion of the payment a refund event names, by Paystack\'s ID for each', function (string $event): void {
    [$order, $payment] = paystackPaidOrder();
    paystackCreatesRefunds();
    $part = paystackRefund($order, 2000);
    Http::fake(['paystack.test/refund/18821743' => Http::response(paystackFixture('refund-fetch-by-id', ['data.merchant_note' => $part->reference, 'data.amount' => 2000]))]);

    sendPaystackWebhook(['event' => $event, 'data' => ['status' => 'processed', 'transaction_reference' => $payment->reference, 'amount' => '2000', 'currency' => 'NGN', 'domain' => 'test']])->assertOk();

    expect($this->store->run(static fn (): string => $part->refresh()->status->value))->toBe('succeeded')
        ->and(paystackRefundsSent())->toBe(1);
})->with(['refund.processed', 'refund.pending', 'refund.failed', 'refund.something_new']);
