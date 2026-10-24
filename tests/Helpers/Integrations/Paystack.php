<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Features;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\ConnectPaymentGateway;
use App\Tenant\Payments\Actions\StartPayment;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Actions\VerifyRefund;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Integrations\Paystack\PaystackGateway;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For integrations/Paystack/tests
|--------------------------------------------------------------------------
*/

/*
 * Made-up Paystack secret keys: the shape of one, and never a real one. Each
 * is put together from two parts so that no key-shaped text is in the source,
 * which secret scanners (GitHub's push protection among them) rightly refuse.
 */

/** A made-up Paystack test secret key. */
const PAYSTACK_FAKE_TEST_KEY = 'sk_test_'.'fakepaystackkey0123456789';

/** A made-up Paystack live secret key. */
const PAYSTACK_FAKE_LIVE_KEY = 'sk_live_'.'fakepaystackkey0123456789';

/*
 * Paystack error replies whose bodies weren't recorded, only their statuses
 * and codes: put together in the shape of the recorded ones (verify-not-found,
 * refund), never presented as recordings.
 */

/** Paystack refusing a refund above the transaction's amount, sent with status 400. Constructed: not a recorded body. */
const PAYSTACK_REFUND_EXCEEDS_REPLY = ['status' => false, 'message' => 'Constructed: not a recorded reply.', 'type' => 'validation_error', 'code' => 'refund_amount_exceeds_transaction_amount'];

/** Paystack refusing the key, sent with status 401, its code's capital K included. Constructed: not a recorded body. */
const PAYSTACK_INVALID_KEY_REPLY = ['status' => false, 'message' => 'Constructed: not a recorded reply.', 'code' => 'invalid_Key'];

/**
 * Puts Paystack on the test store's plan (or takes it off), with Paystack's API at a test address that only faked HTTP answers.
 */
function usePaystack(bool $onPlan = true): void
{
    config(['paystack.base_url' => 'https://paystack.test']);
    $storeId = (string) test()->store->getTenantKey();
    $snapshot = $onPlan ? FakeFeatureSource::snapshot(entitled: [PaystackGateway::KEY]) : FakeFeatureSource::snapshot();

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, $snapshot));
    app(Features::class)->forget($storeId);
}

/**
 * A Paystack answer recorded in test mode (tests/Fixtures/Paystack), with fields changed by dot path, such as ['data.reference' => 'pay_…'].
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function paystackFixture(string $name, array $changes = []): array
{
    /** @var array<string, mixed> $fixture */
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/Paystack/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);

    foreach ($changes as $path => $value) {
        Arr::set($fixture, $path, $value);
    }

    return $fixture;
}

/**
 * Connects the test store's own Paystack account, as its owner, with Paystack accepting the key.
 */
function connectPaystack(string $secretKey = PAYSTACK_FAKE_TEST_KEY): PaymentGatewayConnection
{
    Http::fake(['paystack.test/balance' => Http::response(['status' => true, 'data' => []])]);

    return test()->store->run(static fn (): PaymentGatewayConnection => app(ConnectPaymentGateway::class)->handle(test()->owner, PaystackGateway::KEY, ['secret_key' => $secretKey]));
}

/**
 * Starts a Paystack payment of an order, as Checkout will.
 */
function startPaystackPayment(Order $order): Payment
{
    return app(StartPayment::class)->handle($order, PaystackGateway::KEY, 'https://shop.example.com/orders/return');
}

/**
 * A started Paystack payment of a test order, Paystack having answered as recorded.
 */
function startedPaystackPayment(): Payment
{
    Http::fake(['paystack.test/transaction/initialize' => static fn (Request $request) => Http::response(paystackFixture('init-reply-003', ['data.reference' => $request['reference']]))]);

    return startPaystackPayment(unpaidOrder(isTest: true));
}

/**
 * Verifies a payment, Paystack answering with a recording made to be about it (its reference, and the order's amount asked and taken), changed as given.
 *
 * @param  array<string, mixed>  $changes
 */
function verifyPaystackPayment(Payment $payment, string $fixture, array $changes = [], int $status = 200): Payment
{
    $total = $payment->amount->minorAmount();
    Http::fake(["paystack.test/transaction/verify/{$payment->reference}" => Http::response(paystackFixture($fixture, ['data.reference' => $payment->reference, 'data.amount' => $total, 'data.requested_amount' => $total, ...$changes]), $status)]);

    return app(VerifyPayment::class)->handle($payment->refresh())->refresh();
}

/**
 * Starts a Paystack payment of a test order and verifies it, Paystack answering the verify exactly as given; returns it freshly read.
 *
 * @param  array<array-key, mixed>|string  $body
 */
function verifyPaystackAnswering(array|string $body, int $status): Payment
{
    return test()->store->run(static function () use ($body, $status): Payment {
        $payment = startedPaystackPayment();
        Http::fake(["paystack.test/transaction/verify/{$payment->reference}" => Http::response($body, $status)]);

        return app(VerifyPayment::class)->handle($payment->refresh())->refresh();
    });
}

/**
 * A test order paid through Paystack, Paystack having answered as recorded (003); with its payment.
 *
 * @return array{Order, Payment}
 */
function paystackPaidOrder(): array
{
    return test()->store->run(static function (): array {
        $payment = verifyPaystackPayment(startedPaystackPayment(), 'verify-success-003');

        return [$payment->order->refresh(), $payment];
    });
}

/**
 * Makes Paystack answer each refund it is asked for (POST /refund) with a recording made to be about it (its merchant note and amount), changed as given; or with the answer given. Every request is kept, even one whose connection fails (paystackRefundRequests).
 *
 * @param  array<string, mixed>  $changes
 * @param  (callable(Request): mixed)|null  $answer
 */
function paystackCreatesRefunds(string $fixture = 'refund-003', array $changes = [], int $status = 200, ?callable $answer = null): void
{
    $sent = new ArrayObject;
    app()->instance('tests.paystack_refund_requests', $sent);
    $answer ??= static fn (Request $request) => Http::response(paystackFixture($fixture, ['data.merchant_note' => $request['merchant_note'], 'data.amount' => (int) $request['amount'], ...$changes]), $status);

    Http::fake(['paystack.test/refund' => static function (Request $request) use ($sent, $answer) {
        $sent->append($request);

        return $answer($request);
    }]);
}

/**
 * The refunds Paystack was asked for since paystackCreatesRefunds, in order.
 *
 * @return list<Request>
 */
function paystackRefundRequests(): array
{
    /** @var ArrayObject<int, Request> $sent */
    $sent = app('tests.paystack_refund_requests');

    return array_values($sent->getArrayCopy());
}

function paystackRefundsSent(): int
{
    return count(paystackRefundRequests());
}

/**
 * Staff refunding an amount of a Paystack-paid order, in minor units; returns the part drawn from its payment.
 */
function paystackRefund(Order $order, int $amount): PaymentRefund
{
    return refundOrder($order, $amount)->paymentRefunds->sole();
}

/**
 * One check with Paystack for a refund, as its schedule, the sweep or a webhook would; returns it freshly read.
 */
function checkPaystackRefund(PaymentRefund $paymentRefund): PaymentRefund
{
    return test()->store->run(static fn (): PaymentRefund => app(VerifyRefund::class)->handle($paymentRefund->refresh())->refresh());
}

/**
 * Sends a webhook as Paystack does: its raw body signed with HMAC-SHA512 under a secret key (the test store's made-up test key by default) in "x-paystack-signature", to the endpoint of the store's Paystack connection for the mode it is in, or the one given.
 *
 * @param  array<array-key, mixed>|string  $payload  Sent as JSON, or exactly as given.
 */
function sendPaystackWebhook(array|string $payload, string $secret = PAYSTACK_FAKE_TEST_KEY, ?string $endpointId = null, ?string $signature = null): TestResponse
{
    $body = is_string($payload) ? $payload : (string) json_encode($payload);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? hash_hmac('sha512', $body, $secret)];

    return test()->call('POST', centralUrl('/webhooks/payments/'.($endpointId ?? fakeWebhookEndpoint(PaystackGateway::KEY))), server: $headers, content: $body);
}

/**
 * Every message logged from now on, with its context, so a test can check a secret never reaches the logs.
 *
 * @return ArrayObject<int, string>
 */
function capturedLogs(): ArrayObject
{
    /** @var ArrayObject<int, string> $logged */
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, static fn (MessageLogged $message) => $logged->append($message->message.' '.json_encode($message->context)));

    return $logged;
}

/**
 * The requests sent to Paystack so far, by method and URL, with their Authorization header.
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function paystackRequests(): array
{
    return app(HttpFactory::class)
        ->recorded(static fn (Request $request): bool => str_starts_with($request->url(), 'https://paystack.test/'))
        ->map(static fn (array $sent): array => [$sent[0]->method(), $sent[0]->url(), $sent[0]->header('Authorization')[0] ?? ''])
        ->values()
        ->all();
}
