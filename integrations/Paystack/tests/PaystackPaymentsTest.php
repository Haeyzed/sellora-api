<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Data\GatewayCredentials;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Events\PaymentFailed;
use App\Tenant\Payments\Events\PaymentNeedsReview;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Exceptions\PaymentRefusedByGatewayException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Integrations\Paystack\PaystackGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b.2: taking payments with Paystack, from answers recorded in test
 * mode (tests/Fixtures/Paystack). A payment starts on Paystack's hosted page
 * with our reference as Paystack's reference, and is verified by it. Only
 * the transaction's own status counts: "abandoned" may still be paid and an
 * unknown reference may not have reached Paystack yet, so both stay pending.
 * Strict about the amount: a payment counts only when Paystack took exactly
 * the order's amount, in its currency and mode; a merchant passing Paystack's
 * fees on to buyers is charged more, which goes to review.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    usePaystack();
    connectPaystack();
    // Each payment's own checks with the gateway are tested in PaymentChecksTest.
    Queue::fake([CheckPaymentWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('starts a payment on Paystack\'s hosted page with our reference, sending Paystack only the amount, the buyer\'s email and the order number', function (): void {
    $this->store->run(function (): void {
        $order = unpaidOrder(isTest: true);
        Http::fake(['paystack.test/transaction/initialize' => static fn (Request $request) => Http::response(paystackFixture('init-reply-003', ['data.reference' => $request['reference']]))]);

        $payment = startPaystackPayment($order);
        $sent = Http::recorded(static fn (Request $request): bool => str_ends_with($request->url(), '/transaction/initialize'))->sole()[0];

        expect($payment->status->value)->toBe('pending')
            ->and($payment->redirect_url)->toBe('https://checkout.paystack.com/prwnu0l3xuzju8s')
            ->and($payment->gateway_reference)->toBeNull()
            ->and([$sent->method(), $sent->header('Authorization')[0]])->toBe(['POST', 'Bearer '.PAYSTACK_FAKE_TEST_KEY])
            ->and($sent->data())->toBe([
                'email' => $order->currentContact()->email,
                'amount' => (string) $order->total->minorAmount(),
                'currency' => 'NGN',
                'reference' => $payment->reference,
                'callback_url' => 'https://shop.example.com/orders/return',
                'metadata' => ['order_number' => $order->displayNumber()],
            ]);
    });
});

it('fails a payment Paystack refuses to start, in our own words', function (): void {
    $this->store->run(function (): void {
        Event::fake([PaymentFailed::class]);
        Http::fake(['paystack.test/transaction/initialize' => Http::response(['status' => false, 'message' => 'Duplicate Transaction Reference', 'code' => 'duplicate_reference'], 400)]);
        $order = unpaidOrder(isTest: true);

        expect(static fn () => startPaystackPayment($order))->toThrow(PaymentRefusedByGatewayException::class);

        $payment = Payment::query()->sole();
        expect([$payment->status->value, $payment->failure_code])->toBe(['failed', 'payment_refused']);
    });
});

it('leaves a payment pending for a later check when Paystack\'s answer leaves it unclear whether it started', function (Closure $answer): void {
    $this->store->run(function () use ($answer): void {
        Http::fake(['paystack.test/transaction/initialize' => $answer]);
        $order = unpaidOrder(isTest: true);

        expect(static fn () => startPaystackPayment($order))->toThrow(PaymentGatewayUnreachableException::class);

        $payment = Payment::query()->sole();
        expect([$payment->status->value, $payment->redirect_url])->toBe(['pending', null]);
    });
})->with([
    'no answer' => [static fn () => throw new ConnectionException('timed out')],
    'a server error' => [static fn () => Http::response('', 502)],
    'rate limited' => [static fn () => Http::response(['status' => false], 429)],
    'timed out at Paystack' => [static fn () => Http::response(['status' => false], 408)],
    'a conflict' => [static fn () => Http::response(['status' => false], 409)],
    'a success that says it failed' => [static fn (Request $request) => Http::response(paystackFixture('init-reply-003', ['status' => false, 'data.reference' => $request['reference']]))],
    'another reference' => [static fn () => Http::response(paystackFixture('init-reply-003'))],
    'no page to send the buyer to' => [static fn (Request $request) => Http::response(paystackFixture('init-reply-003', ['data.reference' => $request['reference'], 'data.authorization_url' => null]))],
    'a page not on https' => [static fn (Request $request) => Http::response(paystackFixture('init-reply-003', ['data.reference' => $request['reference'], 'data.authorization_url' => 'http://checkout.paystack.com/x']))],
]);

it('marks a payment paid when Paystack took exactly the order\'s amount, saving Paystack\'s transaction ID and the card\'s brand and last four digits', function (string $fixture, string $transactionId): void {
    $this->store->run(function () use ($fixture, $transactionId): void {
        $payment = verifyPaystackPayment(startedPaystackPayment(), $fixture);

        expect($payment->status->value)->toBe('succeeded')
            ->and($payment->received?->isEqualTo($payment->amount))->toBeTrue()
            ->and([$payment->gateway_reference, $payment->card_brand, $payment->card_last4])->toBe([$transactionId, 'visa', '4081'])
            ->and($payment->order->payment_status->value)->toBe('paid')
            ->and(Http::recorded(static fn (Request $request): bool => $request->method() === 'GET' && $request->url() === "https://paystack.test/transaction/verify/{$payment->reference}"))->toHaveCount(1);
    });
})->with([
    'merchant bearing the fee (003)' => ['verify-success-003', '6688258824'],
    'merchant bearing the fee (004)' => ['verify-success-004', '6688273916'],
]);

it('reads only the transaction\'s own status: abandoned and anything unknown stay pending, failed and reversed fail', function (string $fixture, array $changes, int $status, string $expected, ?string $failureCode): void {
    $this->store->run(function () use ($fixture, $changes, $status, $expected, $failureCode): void {
        Event::fake([PaymentFailed::class]);

        $payment = verifyPaystackPayment(startedPaystackPayment(), $fixture, $changes, $status);

        expect([$payment->status->value, $payment->failure_code])->toBe([$expected, $failureCode]);
    });
})->with([
    'abandoned: the buyer may still pay' => ['verify-abandoned-001', [], 200, 'pending', null],
    'a reference Paystack doesn\'t know yet' => ['verify-not-found', [], 400, 'pending', null],
    'ongoing' => ['verify-success-003', ['data.status' => 'ongoing'], 200, 'pending', null],
    'a status never seen' => ['verify-success-003', ['data.status' => 'something_new'], 200, 'pending', null],
    'no status' => ['verify-success-003', ['data.status' => null], 200, 'pending', null],
    'failed' => ['verify-success-003', ['data.status' => 'failed'], 200, 'failed', 'payment_failed'],
    'reversed' => ['verify-success-003', ['data.status' => 'reversed'], 200, 'failed', 'payment_reversed'],
]);

it('answers that a reference Paystack doesn\'t know yet is pending, never failed, by its error code in any case', function (string $code): void {
    Http::fake(['paystack.test/transaction/verify/pay_x' => Http::response(paystackFixture('verify-not-found', ['code' => $code]), 400)]);

    $result = app(PaystackGateway::class)->verifyPayment(new GatewayCredentials(['secret_key' => PAYSTACK_FAKE_TEST_KEY]), 'pay_x', Money::ofMinor(500000, 'NGN'));

    expect($result->state)->toBe(GatewayPaymentState::Pending);
})->with(['as recorded' => 'transaction_not_found', 'in capitals' => 'TRANSACTION_NOT_FOUND']);

it('keeps card details only for card payments, and only in the shape expected', function (array $changes, array $card): void {
    $this->store->run(function () use ($changes, $card): void {
        $payment = verifyPaystackPayment(startedPaystackPayment(), 'verify-success-003', $changes);

        expect([$payment->status->value, $payment->card_brand, $payment->card_last4])->toBe(['succeeded', ...$card]);
    });
})->with([
    'a bank transfer' => [['data.channel' => 'bank_transfer'], [null, null]],
    'more than four digits' => [['data.authorization.last4' => '40812'], ['visa', null]],
    'a brand not shaped like one' => [['data.authorization.brand' => '<b>visa</b>'], [null, '4081']],
    'a brand with spaces and capitals' => [['data.authorization.brand' => ' VISA '], ['visa', '4081']],
]);

it('asks about a reference only as one part of Paystack\'s path, whatever it holds', function (): void {
    Http::fake(['paystack.test/*' => Http::response(paystackFixture('verify-not-found'), 400)]);

    app(PaystackGateway::class)->verifyPayment(new GatewayCredentials(['secret_key' => PAYSTACK_FAKE_TEST_KEY]), '../balance?x=1', Money::ofMinor(500000, 'NGN'));

    expect(paystackRequests())->toBe([['GET', 'https://paystack.test/transaction/verify/..%2Fbalance%3Fx%3D1', 'Bearer '.PAYSTACK_FAKE_TEST_KEY]]);
});

it('is strict about the amount: anything but exactly the order\'s amount, currency and mode goes to review or stays pending, never paid', function (array $changes, string $expected, ?string $reason): void {
    $this->store->run(function () use ($changes, $expected, $reason): void {
        Event::fake([PaymentNeedsReview::class]);
        $payment = startedPaystackPayment();
        $total = $payment->amount->minorAmount();

        $verified = verifyPaystackPayment($payment, 'verify-success-003', array_map(static fn (mixed $change): mixed => $change instanceof Closure ? $change($total) : $change, $changes));

        expect([$verified->status->value, $verified->review_reason?->value, $verified->order->payment_status->value])->toBe([$expected, $reason, 'unpaid']);
    });
})->with([
    'the buyer charged Paystack\'s fee on top' => [['data.amount' => static fn (int $total): int => $total + 17500], 'needs_review', 'amount_mismatch'],
    'one kobo short' => [['data.amount' => static fn (int $total): int => $total - 1], 'needs_review', 'amount_mismatch'],
    'asked and taken both more than the order' => [['data.amount' => static fn (int $total): int => $total + 100, 'data.requested_amount' => static fn (int $total): int => $total + 100], 'needs_review', 'amount_mismatch'],
    'asked for another amount, though it took exactly the order\'s' => [['data.requested_amount' => static fn (int $total): int => $total + 100], 'needs_review', 'amount_mismatch'],
    'asked for less, though it took exactly the order\'s' => [['data.requested_amount' => static fn (int $total): int => $total - 1], 'needs_review', 'amount_mismatch'],
    'asked for the order\'s amount and took less' => [['data.amount' => static fn (int $total): int => $total - 100], 'needs_review', 'amount_mismatch'],
    'asked and taken both other amounts, each different' => [['data.amount' => static fn (int $total): int => $total + 100, 'data.requested_amount' => static fn (int $total): int => $total + 200], 'needs_review', 'amount_mismatch'],
    'no amount taken reported' => [['data.amount' => null], 'pending', null],
    'another currency Paystack takes' => [['data.currency' => 'GHS'], 'needs_review', 'currency_mismatch'],
    'a live payment for a test order' => [['data.domain' => 'live'], 'needs_review', 'mode_mismatch'],
    'no amount asked reported' => [['data.requested_amount' => null], 'pending', null],
    'the amount taken as text' => [['data.amount' => static fn (int $total): string => (string) $total], 'pending', null],
    'a currency whose units are not confirmed' => [['data.currency' => 'XOF'], 'pending', null],
    'no currency' => [['data.currency' => null], 'pending', null],
    'no mode' => [['data.domain' => null], 'pending', null],
    'another transaction\'s reference' => [['data.reference' => 'fx-003'], 'pending', null],
    'a success that says the request failed' => [['status' => false], 'pending', null],
]);

it('treats a Paystack server error or no answer as unknown, leaving the payment pending', function (Closure $answer): void {
    $this->store->run(function () use ($answer): void {
        $payment = startedPaystackPayment();
        Http::fake(["paystack.test/transaction/verify/{$payment->reference}" => $answer]);

        $verified = app(VerifyPayment::class)->handle($payment->refresh())->refresh();

        expect([$verified->status->value, $verified->checks])->toBe(['pending', 1]);
    });
})->with([
    'no answer' => [static fn () => throw new ConnectionException('timed out')],
    'a server error' => [static fn () => Http::response('', 503)],
    'unauthorised' => [static fn () => Http::response(['status' => false, 'message' => 'Invalid key'], 401)],
]);
