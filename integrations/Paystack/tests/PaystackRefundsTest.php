<?php

declare(strict_types=1);

use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Events\RefundNeedsReview;
use App\Tenant\Payments\Http\Resources\RefundResource;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b.3: refunds through Paystack, from answers recorded in test mode.
 * Paystack makes a second refund if asked twice, even with the same merchant
 * note, so a refund is never sent blindly. It goes with our reference as its
 * merchant note, and is checked by the ID Paystack gives it. One whose
 * sending went unanswered is looked for among the transaction's refunds:
 * found once, its ID is kept; found more than once, or for another amount,
 * or when less is left to refund than it, or when the list can't be read,
 * staff review it and it is never sent again. Only when the whole list twice
 * has none, a while apart, is it sent again, once.
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
    [$this->order, $this->payment] = paystackPaidOrder();
});

afterEach(function (): void {
    deleteAllStores();
});

it('sends a refund with our reference as its merchant note, keeps Paystack\'s ID for it, and settles it by that ID', function (): void {
    paystackCreatesRefunds();

    $part = paystackRefund($this->order, 2000);
    $sent = Http::recorded(static fn (Request $request): bool => $request->method() === 'POST')->sole()[0];

    expect([$part->status->value, $part->gateway_reference, $part->send_attempts])->toBe(['pending', '18821743', 1])
        ->and([$sent->url(), $sent->header('Authorization')[0]])->toBe(['https://paystack.test/refund', 'Bearer '.PAYSTACK_FAKE_TEST_KEY])
        ->and($sent->data())->toBe(['transaction' => $this->payment->reference, 'amount' => '2000', 'currency' => 'NGN', 'merchant_note' => $part->reference]);

    Http::fake(['paystack.test/refund/18821743' => Http::response(paystackFixture('refund-fetch-by-id', ['data.merchant_note' => $part->reference, 'data.amount' => 2000]))]);
    $checked = checkPaystackRefund($part);

    expect([$checked->status->value, paystackRefundsSent()])->toBe(['succeeded', 1])
        ->and($this->store->run(fn (): string => $this->order->refresh()->payment_status->value))->toBe('partially_refunded');
});

it('reads only a refund that is ours by note and amount: processed succeeds, failed fails, anything else is pending', function (array $changes, string $status, ?string $failureCode, ?string $review): void {
    Event::fake([RefundFailed::class, RefundNeedsReview::class]);
    paystackCreatesRefunds();
    $part = paystackRefund($this->order, 2000);
    Http::fake(['paystack.test/refund/18821743' => Http::response(paystackFixture('refund-fetch-by-id', ['data.merchant_note' => $part->reference, 'data.amount' => 2000, ...$changes]))]);

    $checked = checkPaystackRefund($part);

    expect([$checked->status->value, $checked->failure_code, $checked->review_reason?->value, paystackRefundsSent()])->toBe([$status, $failureCode, $review, 1]);
})->with([
    'still pending' => [['data.status' => 'pending'], 'pending', null, null],
    'processing' => [['data.status' => 'processing'], 'pending', null, null],
    'a status never seen' => [['data.status' => 'something_new'], 'pending', null, null],
    'failed' => [['data.status' => 'failed'], 'failed', 'refund_failed', null],
    'another refund\'s note' => [['data.merchant_note' => 'rfd_someone_else'], 'pending', null, 'gateway_unclear'],
    'another amount' => [['data.amount' => 2001], 'pending', null, 'gateway_unclear'],
    'no amount' => [['data.amount' => null], 'pending', null, 'gateway_unclear'],
    'another currency' => [['data.currency' => 'GHS'], 'pending', null, 'gateway_unclear'],
    'an answer saying the request failed' => [['status' => false, 'data.status' => 'processed'], 'pending', null, null],
    'no refund in the answer' => [['data' => null], 'pending', null, null],
]);

it('asks about a refund only by an ID of digits, as one part of Paystack\'s path', function (): void {
    paystackCreatesRefunds(changes: ['data.id' => null]);
    $part = paystackRefund($this->order, 2000);
    $this->store->run(static fn () => $part->refresh()->forceFill(['gateway_reference' => '1/../../balance'])->save());
    Http::fake(['paystack.test/*' => Http::response(paystackFixture('refund-fetch-by-id'))]);

    $checked = checkPaystackRefund($part);

    expect([$checked->status->value, $checked->needs_review_at])->toBe(['pending', null]);
    Http::assertNotSent(static fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), 'balance'));
});

it('leaves a refund pending, to be looked for, when Paystack\'s answer to sending it leaves unclear whether it was made', function (Closure $answer): void {
    paystackCreatesRefunds(answer: $answer);

    $part = paystackRefund($this->order, 2000);

    expect([$part->status->value, $part->gateway_reference, $part->send_attempts, $part->needs_review_at])->toBe(['pending', null, 1, null]);
})->with([
    'a server error' => [static fn () => Http::response('', 502)],
    'rate limited' => [static fn () => Http::response(['status' => false], 429)],
    'a success that says it failed' => [static fn (Request $request) => Http::response(paystackFixture('refund-003', ['status' => false, 'data.merchant_note' => $request['merchant_note']]))],
    'no refund in the answer' => [static fn () => Http::response(paystackFixture('refund-003', ['data' => null]))],
]);

it('fails a refund Paystack refuses, in our own words', function (): void {
    Event::fake([RefundFailed::class]);
    paystackCreatesRefunds('refund', status: 400);

    $part = paystackRefund($this->order, 2000);
    $shown = $this->store->run(static fn (): string => (string) json_encode((new RefundResource($part->refund))->resolve()));

    expect([$part->status->value, $part->failure_code])->toBe(['failed', 'refund_refused'])
        ->and($shown)->not->toContain('This transaction failed')->not->toContain('declined');
});

it('finds a refund whose sending went unanswered among the transaction\'s refunds, keeping its ID, and never sends it again', function (): void {
    paystackCreatesRefunds(answer: static fn () => throw new ConnectionException('timed out'));
    $part = paystackRefund($this->order, 2000);
    Http::fake(['paystack.test/refund?*' => Http::response(paystackFixture('refund-list-004-pending', ['data.0.merchant_note' => $part->reference, 'data.0.amount' => 2000]))]);

    $checked = checkPaystackRefund($part);
    $lookup = Http::recorded(static fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/refund?'))->sole()[0];

    expect([$checked->status->value, $checked->gateway_reference, $checked->needs_review_at, paystackRefundsSent()])->toBe(['pending', '18823398', null, 1])
        ->and($lookup->url())->toBe('https://paystack.test/refund?transaction=6688258824');
});

it('sends a refund to staff review, never again to Paystack, when its lookup can\'t tell whether it was made', function (Closure $list, string $reason): void {
    Event::fake([RefundNeedsReview::class]);
    paystackCreatesRefunds(answer: static fn () => throw new ConnectionException('timed out'));
    $part = paystackRefund($this->order, 2000);
    Http::fake(['paystack.test/refund?*' => static fn () => $list($part->reference)]);

    $checked = checkPaystackRefund($part);
    $this->travel(30)->minutes();
    checkPaystackRefund($part);
    $this->travel(30)->minutes();
    checkPaystackRefund($part);

    expect([$checked->status->value, $checked->review_reason?->value, paystackRefundsSent()])->toBe(['pending', $reason, 1]);
    Event::assertDispatchedTimes(RefundNeedsReview::class, 1);
})->with([
    'two refunds carrying our note (Paystack made both)' => [static fn (string $reference) => Http::response(paystackFixture('refund-list-by-transaction-id', ['data.0.merchant_note' => $reference, 'data.0.amount' => 2000, 'data.1.merchant_note' => $reference, 'data.1.amount' => 2000])), 'gateway_unclear'],
    'ours by note, for another amount' => [static fn (string $reference) => Http::response(paystackFixture('refund-list-004-pending', ['data.0.merchant_note' => $reference])), 'gateway_unclear'],
    'none ours, and less left to refund than ours' => [static fn () => Http::response(paystackFixture('refund-list-004-pending', ['meta.partialAmountLeft' => 1999])), 'gateway_unclear'],
    'none ours, and nothing said about what is left' => [static fn () => Http::response(paystackFixture('refund-list-004-pending', ['meta.partialAmountLeft' => null])), 'gateway_unclear'],
    'the list not loading' => [static fn () => Http::response('', 503), 'lookup_failed'],
    'no answer' => [static fn () => throw new ConnectionException('timed out'), 'lookup_failed'],
    'a list that says the request failed' => [static fn () => Http::response(paystackFixture('refund-list-004-pending', ['status' => false])), 'lookup_failed'],
    'more refunds than the page holds' => [static fn () => Http::response(paystackFixture('refund-list-004-pending', ['meta.total' => 2])), 'lookup_failed'],
    'no list' => [static fn () => Http::response(paystackFixture('refund-list-004-pending', ['data' => null])), 'lookup_failed'],
    'refunds keyed by name, not a list' => [static fn () => Http::response(['status' => true, 'data' => ['first' => paystackFixture('refund-list-004-pending')['data'][0]], 'meta' => ['total' => 1, 'partialAmountLeft' => 400000]]), 'lookup_failed'],
    'something in the list that is not a refund' => [static fn () => Http::response(paystackFixture('refund-list-004-pending', ['data.1' => 'refund', 'meta.total' => 2])), 'lookup_failed'],
    'what is left as text' => [static fn () => Http::response(paystackFixture('refund-list-004-pending', ['meta.partialAmountLeft' => '400000'])), 'gateway_unclear'],
]);

it('looks for a refund only by the transaction\'s Paystack ID: without one, staff review it', function (?string $transactionId): void {
    Event::fake([RefundNeedsReview::class]);
    paystackCreatesRefunds(answer: static fn () => throw new ConnectionException('timed out'));
    $part = paystackRefund($this->order, 2000);
    $this->store->run(fn () => $this->payment->forceFill(['gateway_reference' => $transactionId])->save());

    $checked = checkPaystackRefund($part);

    expect([$checked->review_reason?->value, paystackRefundsSent()])->toBe(['lookup_failed', 1]);
    Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), '/refund?'));
})->with([
    'none' => [null],
    'not Paystack\'s numeric ID' => ['pay_6688258824'],
]);

it('sends a refund again, once, only when the whole list twice has none of ours, a while apart', function (): void {
    Event::fake([RefundNeedsReview::class]);
    config(['payments.checks.refund_resend_after_minutes' => 5]);
    paystackCreatesRefunds(answer: Http::sequence()->pushFailedConnection()->pushFailedConnection());
    $part = paystackRefund($this->order, 2000);
    // Exactly what this refund needs is left.
    Http::fake(['paystack.test/refund?*' => Http::response(paystackFixture('refund-list-004-pending', ['meta.partialAmountLeft' => 2000]))]);

    checkPaystackRefund($part);
    $this->travel(4)->minutes();
    $early = [checkPaystackRefund($part)->send_attempts, paystackRefundsSent()];
    $this->travel(2)->minutes();
    $resent = [checkPaystackRefund($part)->send_attempts, paystackRefundsSent()];

    expect($early)->toBe([1, 1])
        ->and($resent)->toBe([2, 2])
        ->and(array_map(static fn (Request $request): string => $request['merchant_note'], paystackRefundRequests()))->toBe([$part->reference, $part->reference]);

    // The first answer after sending it again only starts the wait.
    expect(checkPaystackRefund($part)->needs_review_at)->toBeNull();
    $this->travel(6)->minutes();
    $given = checkPaystackRefund($part);
    $this->travel(60)->minutes();
    checkPaystackRefund($part);

    expect([$given->status->value, $given->review_reason?->value, $given->send_attempts, paystackRefundsSent()])->toBe(['pending', 'not_found_after_resend', 2, 2]);
});

it('keeps a refund found after one empty answer, never sending it again', function (): void {
    paystackCreatesRefunds(answer: static fn () => throw new ConnectionException('timed out'));
    $part = paystackRefund($this->order, 2000);
    Http::fake(['paystack.test/refund?*' => Http::sequence()
        ->push(paystackFixture('refund-list-004-pending'))
        ->push(paystackFixture('refund-list-004-pending', ['data.0.merchant_note' => $part->reference, 'data.0.amount' => 2000]))]);

    checkPaystackRefund($part);
    $this->travel(10)->minutes();
    $found = checkPaystackRefund($part);

    expect([$found->gateway_reference, $found->send_attempts, paystackRefundsSent()])->toBe(['18823398', 1, 1]);
});
