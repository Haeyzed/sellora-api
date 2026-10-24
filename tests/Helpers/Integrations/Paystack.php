<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Features;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\ConnectPaymentGateway;
use App\Tenant\Payments\Actions\StartPayment;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
