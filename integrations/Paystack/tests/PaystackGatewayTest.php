<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Payments\Data\GatewayCredentials;
use App\Tenant\Payments\Data\GatewayPaymentRequest;
use App\Tenant\Payments\Data\GatewayRefundRequest;
use App\Tenant\Payments\Data\IncomingWebhook;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Exceptions\GatewayRejectedException;
use App\Tenant\Payments\Exceptions\GatewayUnavailableException;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\PaymentGatewayRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Integrations\Paystack\PaystackGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b.1: Paystack as a gateway integration. A store connects its own
 * Paystack account with its secret key alone, checked with Paystack; test or
 * live comes from the key. The key is kept encrypted and never shown, logged
 * or sent anywhere but Paystack's Authorization header. Paystack is offered
 * only to stores whose plan includes it. Taking payments, refunds and
 * webhooks come in 16b.2 to 16b.4; until then each refuses without calling
 * Paystack.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    usePaystack();
    $this->logged = capturedLogs();
});

afterEach(function (): void {
    deleteAllStores();
});

it('is listed with the launch currencies, Paystack\'s countries and ways to pay, and one secret key to enter', function (): void {
    $paystack = collect(staffOrderRequest('GET', '/payment-gateways')->assertOk()->json('data'))->keyBy('key')['paystack'];

    expect($paystack)->toMatchArray([
        'currencies' => ['NGN', 'GHS', 'ZAR', 'KES', 'USD'],
        'countries' => ['NG', 'GH', 'ZA', 'KE'],
        'methods' => ['card', 'bank_transfer', 'ussd', 'mobile_money'],
        'credential_fields' => [['name' => 'secret_key', 'secret' => true]],
        'availability' => 'enabled',
        'connection' => null,
    ])->and(app(PaymentGatewayRegistry::class)->get('paystack'))->toBeInstanceOf(PaystackGateway::class);
});

it('connects the store\'s own account once Paystack accepts the key, showing a webhook URL per mode and never the key', function (string $key, string $mode): void {
    Http::fake(['paystack.test/balance' => Http::response(['status' => true, 'message' => 'Balances retrieved', 'data' => [['currency' => 'NGN', 'balance' => 0]]])]);

    $response = staffOrderRequest('PUT', '/payment-gateways/paystack/connection', ['credentials' => ['secret_key' => $key]])->assertCreated();
    [$testEndpoint, $liveEndpoint] = $this->store->run(static fn (): array => [PaymentGatewayConnection::query()->sole()->test_endpoint_id, PaymentGatewayConnection::query()->sole()->live_endpoint_id]);

    expect($response->json('data'))->toMatchArray(['gateway' => 'paystack', 'status' => 'connected', 'mode' => $mode, 'credentials_hint' => '…6789'])
        ->and($response->json('data.webhook_urls'))->toBe(['test' => centralUrl('/webhooks/payments/'.$testEndpoint), 'live' => centralUrl('/webhooks/payments/'.$liveEndpoint)])
        ->and($response->getContent())->not->toContain($key)
        ->and(paystackRequests())->toBe([['GET', 'https://paystack.test/balance', 'Bearer '.$key]])
        ->and(implode("\n", $this->logged->getArrayCopy()))->not->toContain($key)
        ->and($this->store->run(static fn (): string => (string) DB::table('payment_gateway_connections')->value('credentials')))->not->toContain($key)
        ->and($this->store->run(static fn (): array => (array) PaymentGatewayConnection::query()->sole()->credentials))->toBe(['secret_key' => $key]);
})->with([
    'a test key' => [PAYSTACK_FAKE_TEST_KEY, 'test'],
    'a live key' => [PAYSTACK_FAKE_LIVE_KEY, 'live'],
]);

it('saves nothing when Paystack refuses the key, and sends Paystack nothing shaped otherwise', function (): void {
    Http::fake(['paystack.test/balance' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

    staffOrderRequest('PUT', '/payment-gateways/paystack/connection', ['credentials' => ['secret_key' => PAYSTACK_FAKE_TEST_KEY]])->assertUnprocessable()->assertJsonPath('code', 'payment_gateway_credentials_invalid');
    // Paystack's own words never reach the store.
    expect(staffOrderRequest('PUT', '/payment-gateways/paystack/connection', ['credentials' => ['secret_key' => PAYSTACK_FAKE_TEST_KEY]])->getContent())->not->toContain('Invalid key');

    foreach (['pk_test_'.'fakepaystackkey0123456789', 'sk_sandbox_fakepaystackkey0123', 'sk_test_', 'sk_test_with spaces', 'sk_live_fake?key'] as $notASecretKey) {
        staffOrderRequest('PUT', '/payment-gateways/paystack/connection', ['credentials' => ['secret_key' => $notASecretKey]])->assertJsonValidationErrors('credentials.secret_key');
    }

    staffOrderRequest('PUT', '/payment-gateways/paystack/connection', ['credentials' => ['secret_key' => PAYSTACK_FAKE_TEST_KEY, 'public_key' => 'pk_test_x']])->assertJsonValidationErrors('credentials');
    expect(paystackRequests())->toHaveCount(2)
        ->and($this->store->run(static fn (): int => PaymentGatewayConnection::query()->count()))->toBe(0);
});

it('checks a key with Paystack: refused for good only when Paystack says so, otherwise the answer is unknown', function (Closure $answer, ?bool $isValid): void {
    Http::fake(['paystack.test/balance' => $answer]);
    $check = static fn () => app(PaystackGateway::class)->checkCredentials(new GatewayCredentials(['secret_key' => PAYSTACK_FAKE_LIVE_KEY]));

    if ($isValid === null) {
        expect($check)->toThrow(GatewayUnavailableException::class);
    } else {
        expect($check()->isValid)->toBe($isValid)->and($check()->mode)->toBe(GatewayMode::Live);
    }
})->with([
    'accepted' => [static fn () => Http::response(['status' => true, 'data' => []]), true],
    'unauthorised' => [static fn () => Http::response(['status' => false], 401), false],
    'forbidden' => [static fn () => Http::response(['status' => false], 403), false],
    'a server error' => [static fn () => Http::response('', 502), null],
    'rate limited' => [static fn () => Http::response(['status' => false], 429), null],
    'a success that says it failed' => [static fn () => Http::response(['status' => false]), null],
    'no answer' => [static fn () => throw new ConnectionException('timed out'), null],
]);

it('waits for Paystack only as long as configured, so a slow answer counts as unknown', function (): void {
    config(['paystack.timeout_in_seconds' => 12, 'paystack.connect_timeout_in_seconds' => 4]);
    $options = [];
    Http::fake(static function ($request, array $sentWith) use (&$options) {
        $options = $sentWith;

        return Http::response(['status' => true, 'data' => []]);
    });

    app(PaystackGateway::class)->checkCredentials(new GatewayCredentials(['secret_key' => PAYSTACK_FAKE_TEST_KEY]));

    expect($options)->toMatchArray(['timeout' => 12, 'connect_timeout' => 4]);
});

it('refuses a key not shaped like a Paystack secret key without sending it anywhere', function (): void {
    Http::fake();

    $check = app(PaystackGateway::class)->checkCredentials(new GatewayCredentials(['secret_key' => 'pk_live_'.'fakepaystackkey0123456789']));

    expect($check->isValid)->toBeFalse();
    Http::assertNothingSent();
});

it('is offered for connecting only to stores whose plan includes it, and its money in motion never needs the plan', function (): void {
    usePaystack(onPlan: false);
    Http::fake();

    staffOrderRequest('PUT', '/payment-gateways/paystack/connection', ['credentials' => ['secret_key' => PAYSTACK_FAKE_TEST_KEY]])->assertForbidden();
    Http::assertNothingSent();
});

it('refuses a payment in a currency whose Paystack units are not confirmed, without calling Paystack', function (): void {
    Http::fake();
    $start = static fn () => app(PaystackGateway::class)->startPayment(
        new GatewayCredentials(['secret_key' => PAYSTACK_FAKE_TEST_KEY]),
        new GatewayPaymentRequest('pay_x', Money::ofMinor(500000, 'XOF'), 'buyer@example.com', 'ORD-1001', 'https://shop.example.com/return'),
    );

    expect($start)->toThrow(fn (GatewayRejectedException $exception) => expect($exception->failureCode)->toBe('amount_not_supported'));
    Http::assertNothingSent();
});

it('refuses a refund in a currency whose Paystack units are not confirmed, without calling Paystack', function (): void {
    Http::fake();
    $refund = static fn () => app(PaystackGateway::class)->refund(
        new GatewayCredentials(['secret_key' => PAYSTACK_FAKE_TEST_KEY]),
        new GatewayRefundRequest('rfd_x', 'pay_x', '6688258824', Money::ofMinor(2000, 'XOF')),
    );

    expect($refund)->toThrow(fn (GatewayRejectedException $exception) => expect($exception->failureCode)->toBe('amount_not_supported'));
    Http::assertNothingSent();
});

it('takes a webhook only signed with HMAC-SHA512 of its exact body under the key', function (array $headers, bool $isSigned): void {
    $credentials = new GatewayCredentials(['secret_key' => PAYSTACK_FAKE_TEST_KEY]);

    expect(app(PaystackGateway::class)->verifyWebhookSignature($credentials, new IncomingWebhook('{"event":"charge.success"}', $headers)))->toBe($isSigned);
})->with([
    'signed' => [['x-paystack-signature' => hash_hmac('sha512', '{"event":"charge.success"}', PAYSTACK_FAKE_TEST_KEY)], true],
    'another key' => [['x-paystack-signature' => hash_hmac('sha512', '{"event":"charge.success"}', PAYSTACK_FAKE_LIVE_KEY)], false],
    'another body' => [['x-paystack-signature' => hash_hmac('sha512', '{"event":"charge.success" }', PAYSTACK_FAKE_TEST_KEY)], false],
    'SHA-256' => [['x-paystack-signature' => hash_hmac('sha256', '{"event":"charge.success"}', PAYSTACK_FAKE_TEST_KEY)], false],
    'no signature' => [[], false],
]);
