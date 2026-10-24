<?php

declare(strict_types=1);

use App\Tenant\Payments\Events\PaymentFailed;
use App\Tenant\Payments\Events\PaymentGatewayNeedsReconnecting;
use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Exceptions\PaymentGatewayUnreachableException;
use App\Tenant\Payments\Exceptions\RefundExceedsRefundableException;
use App\Tenant\Payments\Http\Resources\RefundResource;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Integrations\Paystack\PaystackGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b: Paystack's error replies, as recorded in test mode. Paystack
 * doesn't keep its HTTP statuses to their meaning, so its error code is read
 * first, in any case. A verify is never refused: 400 "transaction_not_found"
 * is a reference it doesn't know yet, and the payment stays pending. Only 400 and 422
 * replies refuse, in our own words. A refused key (401 "invalid_Key", or 403)
 * never fails anything: the merchant is asked to reconnect. Anything else is
 * unknown, and asked about again.
 *
 * Recorded bodies: verify-not-found (400) and refund (400, "declined"). Only
 * the statuses and codes of the 400 "refund_amount_exceeds_transaction_amount"
 * and 401 "invalid_Key" replies were recorded; their bodies here are put
 * together in the same shape (PAYSTACK_REFUND_EXCEEDS_REPLY,
 * PAYSTACK_INVALID_KEY_REPLY).
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    usePaystack();
    connectPaystack();
    // Each payment's and refund's own checks are run by hand where they matter.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('leaves a payment pending, never refused, when Paystack answers 400 "transaction_not_found" as recorded', function (): void {
    Event::fake([PaymentFailed::class, PaymentGatewayNeedsReconnecting::class]);

    $payment = verifyPaystackAnswering(paystackFixture('verify-not-found'), 400);

    expect([$payment->status->value, $payment->failure_code, $payment->needs_review_at, $payment->checks])->toBe(['pending', null, null, 1])
        ->and(gatewayKeysRefusedAt(PaystackGateway::KEY))->toBeNull();
    Event::assertNotDispatched(PaymentFailed::class);
    Event::assertNotDispatched(PaymentGatewayNeedsReconnecting::class);
});

it('leaves a payment pending, as unknown, whatever else Paystack answers a verify with', function (array|string $body, int $status): void {
    Event::fake([PaymentFailed::class]);

    $payment = verifyPaystackAnswering($body, $status);

    expect([$payment->status->value, $payment->failure_code, $payment->checks])->toBe(['pending', null, 1])
        ->and(gatewayKeysRefusedAt(PaystackGateway::KEY))->toBeNull();
    Event::assertNotDispatched(PaymentFailed::class);
})->with([
    'another 400' => [['status' => false, 'code' => 'invalid_request'], 400],
    'a 422' => [['status' => false, 'code' => 'invalid_request'], 422],
    'a 404' => [['status' => false], 404],
    'a timeout answer' => [['status' => false], 408],
    'a conflict' => [['status' => false], 409],
    'rate limited' => [['status' => false], 429],
    'a server error' => ['', 500],
    'not JSON' => ['<html>Bad gateway</html>', 200],
]);

it('leaves a payment pending when Paystack refuses the key, by its code before its status, and asks the merchant once to reconnect', function (array $body, int $status): void {
    Event::fake([PaymentFailed::class, PaymentGatewayNeedsReconnecting::class]);

    $payment = verifyPaystackAnswering($body, $status);
    $again = verifyPaystackAnswering($body, $status);

    expect([$payment->status->value, $payment->failure_code, $payment->needs_review_at, $payment->checks, $again->status->value])->toBe(['pending', null, null, 1, 'pending'])
        ->and(gatewayKeysRefusedAt(PaystackGateway::KEY))->not->toBeNull();
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 1);
    Event::assertNotDispatched(PaymentFailed::class);
})->with([
    '401 "invalid_Key"' => [PAYSTACK_INVALID_KEY_REPLY, 401],
    'a 401 with no code' => [['status' => false], 401],
    'a 403' => [['status' => false], 403],
    '"invalid_key" with a 400' => [[...PAYSTACK_INVALID_KEY_REPLY, 'code' => 'INVALID_KEY'], 400],
]);

it('leaves a payment being started pending when Paystack refuses the key, and asks the merchant to reconnect', function (): void {
    Http::fake(['paystack.test/transaction/initialize' => Http::response(PAYSTACK_INVALID_KEY_REPLY, 401)]);
    $order = $this->store->run(static fn () => unpaidOrder(isTest: true));

    expect(fn () => $this->store->run(static fn () => startPaystackPayment($order)))->toThrow(PaymentGatewayUnreachableException::class)
        ->and($this->store->run(static fn (): array => [Payment::query()->sole()->status->value, Payment::query()->sole()->failure_code]))->toBe(['pending', null])
        ->and(gatewayKeysRefusedAt(PaystackGateway::KEY))->not->toBeNull();
});

it('leaves a payment being started pending, as unknown, when Paystack answers neither 400 nor 422', function (int $status): void {
    Http::fake(['paystack.test/transaction/initialize' => Http::response(['status' => false], $status)]);
    $order = $this->store->run(static fn () => unpaidOrder(isTest: true));

    expect(fn () => $this->store->run(static fn () => startPaystackPayment($order)))->toThrow(PaymentGatewayUnreachableException::class)
        ->and($this->store->run(static fn (): string => Payment::query()->sole()->status->value))->toBe('pending');
})->with([404, 405, 408, 409, 429, 503]);

it('fails a refund Paystack refuses with a 400 or a 422, in our own words', function (array $body, int $status, string $failureCode): void {
    Event::fake([RefundFailed::class]);
    [$order] = paystackPaidOrder();
    paystackCreatesRefunds(answer: static fn () => Http::response($body, $status));

    $part = paystackRefund($order, 2000);
    $shown = $this->store->run(static fn (): string => (string) json_encode((new RefundResource($part->refund))->resolve()));

    expect([$part->status->value, $part->failure_code, $part->needs_review_at])->toBe(['failed', $failureCode, null])
        ->and($shown)->not->toContain((string) ($body['message'] ?? ''))->not->toContain((string) $body['code']);
    Event::assertDispatched(RefundFailed::class);
})->with([
    'of an unpaid transaction: 400 "declined", recorded' => [paystackFixture('refund'), 400, 'refund_refused'],
    'above the transaction: 400 "refund_amount_exceeds_transaction_amount"' => [PAYSTACK_REFUND_EXCEEDS_REPLY, 400, 'refund_exceeds_payment'],
    'that code in capitals' => [[...PAYSTACK_REFUND_EXCEEDS_REPLY, 'code' => 'REFUND_AMOUNT_EXCEEDS_TRANSACTION_AMOUNT'], 400, 'refund_exceeds_payment'],
    'a 422' => [['status' => false, 'message' => 'Something Paystack says', 'code' => 'invalid_request'], 422, 'refund_refused'],
]);

it('leaves a refund pending, to be looked for, when Paystack answers neither 400 nor 422 to sending it', function (int $status): void {
    [$order] = paystackPaidOrder();
    paystackCreatesRefunds(answer: static fn () => Http::response(['status' => false], $status));

    $part = paystackRefund($order, 2000);

    expect([$part->status->value, $part->failure_code, $part->needs_review_at, paystackRefundsSent()])->toBe(['pending', null, null, 1]);
})->with([404, 408, 409, 500]);

it('sends a refund Paystack refused for the key to staff review, never failed, and asks the merchant to reconnect', function (array $body, int $status): void {
    Event::fake([RefundFailed::class, PaymentGatewayNeedsReconnecting::class]);
    [$order] = paystackPaidOrder();
    paystackCreatesRefunds(answer: static fn () => Http::response($body, $status));

    $part = paystackRefund($order, 2000);

    expect([$part->status->value, $part->failure_code, $part->review_reason?->value, paystackRefundsSent()])->toBe(['pending', null, 'credentials_refused', 1])
        ->and(gatewayKeysRefusedAt(PaystackGateway::KEY))->not->toBeNull();
    Event::assertNotDispatched(RefundFailed::class);
    Event::assertDispatchedTimes(PaymentGatewayNeedsReconnecting::class, 1);
})->with([
    '401 "invalid_Key"' => [PAYSTACK_INVALID_KEY_REPLY, 401],
    'a 403' => [['status' => false], 403],
]);

it('holds refunds to what is left of the payment before asking Paystack, whatever Paystack would allow', function (): void {
    [$order, $payment] = paystackPaidOrder();
    // Paystack would make any refund asked for.
    paystackCreatesRefunds(answer: static fn (Request $request) => Http::response(paystackFixture('refund-003', ['data.merchant_note' => $request['merchant_note'], 'data.amount' => (int) $request['amount'], 'data.status' => 'processed'])));
    paystackRefund($order, 2000);

    expect(fn () => refundOrder($order, $payment->amount->minorAmount() - 2000 + 1))->toThrow(RefundExceedsRefundableException::class)
        ->and(paystackRefundsSent())->toBe(1);

    paystackRefund($order, $payment->amount->minorAmount() - 2000);

    expect(fn () => refundOrder($order, 1))->toThrow(RefundExceedsRefundableException::class)
        ->and(paystackRefundsSent())->toBe(2);
});
