<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Money;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Orders\Events\OrderRefundedBeyondPaid;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\CheckPendingRefund;
use App\Tenant\Payments\Actions\VerifyRefund;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Events\RefundSucceededAfterFailing;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\Models\RefundLine;
use App\Tenant\Returns\Actions\RefundReturn;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Enums\ReturnRefundStatus;
use App\Tenant\Returns\Events\ReturnCompleted;
use App\Tenant\Returns\Events\ReturnRefundStarted;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefund;
use App\Tenant\Returns\Services\ReturnRefundAttempts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.7: refunding a return, through Payments only (section 13). Every
 * unit that came back, at what the buyer paid for it, with shipping when the
 * store was at fault unless staff say otherwise, less anything kept back with
 * a reason. Each attempt is saved before Payments is asked, under the key
 * "return:{id}:{n}"; asking again never refunds twice (section 3.3): a new
 * attempt only once the last definitely got nothing back. The return
 * completes on the first refund that went back in full, and only once.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    // Each refund's own checks are run by hand.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('refunds an inspected return through Payments, completing it once the refund went back, with its credit note', function (): void {
    Event::fake([ReturnRefundStarted::class, ReturnCompleted::class]);
    $order = returnableOrder([[10000, 2], [5000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order, [$order->lines[0]->public_id => 2]);

    $response = refundReturnAs($return, $this->owner, [], 'return-refund-0001')->assertOk();

    expect($response->json('data.status'))->toBe('completed')
        ->and($response->json('data.refunds'))->toHaveCount(1)
        ->and($response->json('data.refunds.0'))->toMatchArray(['attempt' => 1, 'status' => 'succeeded', 'refusal_code' => null, 'shipping' => false, 'withheld' => 0]);

    $this->store->run(function () use ($return, $order, $response): void {
        $refund = Refund::query()->with('lines')->sole();

        expect([$refund->public_id, $refund->idempotency_key, $refund->amount->minorAmount(), $refund->shipping->minorAmount()])->toBe([$response->json('data.refunds.0.refund'), "return:{$return->public_id}:1", 20000, 0])
            ->and($refund->lines->map(static fn ($line): array => [$line->order_line_id, $line->quantity])->all())->toBe([[$order->lines[0]->id, 2]])
            ->and(CreditNote::query()->sole()->total->minorAmount())->toBe(20000);
        Event::assertDispatchedTimes(ReturnRefundStarted::class, 1);
        Event::assertDispatchedTimes(ReturnCompleted::class, 1);
        Event::assertDispatched(ReturnCompleted::class, static fn (ReturnCompleted $event): bool => $event->return->is($return));
    });

    // The same Idempotency-Key gives the same answer; asking again without one refunds nothing more.
    refundReturnAs($return, $this->owner, [], 'return-refund-0001')->assertOk()->assertJsonPath('data.refunds.0.refund', $response->json('data.refunds.0.refund'));
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonCount(1, 'data.refunds');
    expect($this->script->timesCalled('refund'))->toBe(1);
});

it('gives back shipping by default only when the store was at fault, unless staff say otherwise', function (ReturnReason $reason, array $data, bool $shipping): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order, reason: $reason);

    refundReturnAs($return, $this->owner, $data)->assertOk()->assertJsonPath('data.refunds.0.shipping', $shipping);

    $this->store->run(static fn () => expect(Refund::query()->sole()->shipping->isPositive())->toBe($shipping));
})->with([
    'damaged' => [ReturnReason::Damaged, [], true],
    'the wrong item' => [ReturnReason::WrongItem, [], true],
    'changed their mind' => [ReturnReason::ChangedMind, [], false],
    'damaged, staff leaving shipping out' => [ReturnReason::Damaged, ['shipping' => false], false],
    'changed their mind, staff giving shipping back' => [ReturnReason::ChangedMind, ['shipping' => true], true],
]);

it('keeps part of the refund back only with a reason, and the credit note credits only what went back', function (): void {
    $order = returnableOrder([[10000, 2]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);

    refundReturnAs($return, $this->owner, ['withheld' => 1000])->assertUnprocessable()->assertJsonValidationErrors('withheld_reason');
    refundReturnAs($return, $this->owner, ['withheld_reason' => 'No discount left'])->assertUnprocessable()->assertJsonValidationErrors('withheld');
    refundReturnAs($return, $this->owner, ['withheld' => 1000, 'withheld_reason' => 'The free unit came back too'])->assertOk()
        ->assertJsonPath('data.refunds.0.withheld', 1000)
        ->assertJsonPath('data.refunds.0.withheld_reason', 'The free unit came back too');

    $this->store->run(static function (): void {
        $refund = Refund::query()->sole();

        expect([$refund->amount->minorAmount(), $refund->withheld->minorAmount(), $refund->withheld_reason])->toBe([19000, 1000, 'The free unit came back too'])
            ->and(CreditNote::query()->sole()->total->minorAmount())->toBe(19000);
    });
});

it('issues a test order\'s credit note in the test series', function (): void {
    // Test orders pay through the gateway's test mode.
    connectFakeGateway('test');
    $order = returnableOrder([[10000, 1]], $this->customer, isTest: true, paidByGateway: true);
    $return = inspectedReturn($order);

    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.status', 'completed');

    $this->store->run(static fn () => expect(CreditNote::query()->sole())->number->toStartWith('TEST-CN-')->is_test->toBeTrue());
});

it('refunds an approved return whose buyer keeps the items, moving no stock', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = requestedReturn($order);

    refundReturnAs($return, $this->owner)->assertConflict();
    returnsRequest('POST', "{$return->public_id}/approve", ['keep_items' => true], as: $this->owner)->assertOk();
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.status', 'completed');

    $this->store->run(static fn () => expect(StockMovement::query()->where('source_type', 'return')->exists())->toBeFalse());
});

it('refuses to refund a return that isn\'t inspected, unless its buyer keeps the items', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $received = receivedReturn($order);

    refundReturnAs($received, $this->owner)->assertConflict()->assertJsonPath('code', 'return_transition_not_allowed');

    $this->store->run(static fn () => expect(ReturnRefund::query()->exists())->toBeFalse());
});

it('lets only staff who can manage returns and refund payments refund a return', function (): void {
    $return = inspectedReturn(returnableOrder([[10000, 1]], $this->customer, paidByGateway: true));

    refundReturnAs($return, returnsStaffWith($this->store, ['returns.manage']))->assertForbidden();
    refundReturnAs($return, returnsStaffWith($this->store, ['payments.refund']))->assertForbidden();
    returnsRequest('POST', "{$return->public_id}/refund")->assertUnauthorized();

    $this->store->run(static fn () => expect(ReturnRefund::query()->exists())->toBeFalse());
});

it('never sends a second refund when the first timed out but went through: it is looked up, not tried again', function (): void {
    Event::fake([ReturnCompleted::class]);
    $return = inspectedReturn(returnableOrder([[10000, 1]], $this->customer, paidByGateway: true));

    // The gateway doesn't answer: Payments keeps the refund pending and checks on it.
    $this->script->unavailable = true;
    refundReturnAs($return, $this->owner)->assertOk()
        ->assertJsonPath('data.status', 'inspected')
        ->assertJsonPath('data.refunds.0.status', 'pending');
    $this->script->unavailable = false;

    // Staff ask again: the attempt is looked up, never resent or replaced.
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonCount(1, 'data.refunds')->assertJsonPath('data.refunds.0.status', 'pending');

    // It had gone through after all.
    $part = soleRefundPart();
    $this->script->refunds[$part->reference] = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_r1', $part->amount);
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));

    returnsRequest('GET', $return->public_id, as: $this->owner)->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.refunds.0.status', 'succeeded');
    expect($this->script->timesCalled('refund'))->toBe(1);
    $this->store->run(static function (): void {
        expect([Refund::query()->count(), ReturnRefund::query()->count()])->toBe([1, 1]);
        Event::assertDispatchedTimes(ReturnCompleted::class, 1);
    });
});

it('tries again under a new key only once every part of the last refund failed', function (): void {
    Event::fake([ReturnRefundStarted::class, ReturnCompleted::class]);
    $return = inspectedReturn(returnableOrder([[10000, 1]], $this->customer, paidByGateway: true));
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Failed, failureCode: 'refund_declined');

    refundReturnAs($return, $this->owner)->assertOk()
        ->assertJsonPath('data.status', 'inspected')
        ->assertJsonPath('data.refunds.0.status', 'failed');
    $this->store->run(static fn () => Event::assertNotDispatched(ReturnCompleted::class));

    $this->script->refundAnswer = null;
    $response = refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.status', 'completed');

    expect(array_column($response->json('data.refunds'), 'status', 'attempt'))->toBe([1 => 'failed', 2 => 'succeeded']);
    $this->store->run(static function () use ($return): void {
        expect(Refund::query()->orderBy('id')->pluck('idempotency_key')->all())->toBe(["return:{$return->public_id}:1", "return:{$return->public_id}:2"]);
        Event::assertDispatchedTimes(ReturnRefundStarted::class, 2);
    });
});

it('leaves a refund Payments sent to staff review alone: the return stays inspected and shows it, and no new attempt is made', function (): void {
    $return = inspectedReturn(returnableOrder([[10000, 1]], $this->customer, paidByGateway: true));
    $this->script->unavailable = true;
    refundReturnAs($return, $this->owner)->assertOk();
    $this->script->unavailable = false;
    $part = soleRefundPart();
    $this->script->refunds[$part->reference] = new GatewayRefundResult(GatewayRefundState::Unclear);
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));

    returnsRequest('GET', $return->public_id, as: $this->owner)->assertOk()->assertJsonPath('data.status', 'inspected')->assertJsonPath('data.refunds.0.status', 'in_review');
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonCount(1, 'data.refunds')->assertJsonPath('data.refunds.0.status', 'in_review');

    expect($this->script->timesCalled('refund'))->toBe(1)
        ->and(soleRefundPart()->needs_review_at)->not->toBeNull();
    $this->store->run(static fn () => expect(Refund::query()->count())->toBe(1));
    // Nor can it be rejected while its refund may have gone through.
    returnsRequest('POST', "{$return->public_id}/reject", ['reason' => 'No'], as: $this->owner)->assertConflict();
});

it('completes on the first success it hears of, once, when a refund reported failed goes through after the next one did', function (): void {
    Event::fake([ReturnCompleted::class, OrderRefundedBeyondPaid::class, RefundSucceededAfterFailing::class]);
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    // Lines and shipping: each attempt gives back the whole order.
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Failed, failureCode: 'refund_declined');
    refundReturnAs($return, $this->owner, ['shipping' => true])->assertOk()->assertJsonPath('data.refunds.0.status', 'failed');
    $this->script->refundAnswer = null;
    refundReturnAs($return, $this->owner, ['shipping' => true])->assertOk()->assertJsonPath('data.status', 'completed');

    // The gateway's latest word wins: the first refund went through after all.
    $first = $this->store->run(static fn (): PaymentRefund => PaymentRefund::query()->orderBy('id')->firstOrFail());
    $this->script->refunds[$first->reference] = new GatewayRefundResult(GatewayRefundState::Succeeded, 'fake_late', $first->amount);
    $this->store->run(static fn () => app(VerifyRefund::class)->handle($first->refresh()));

    returnsRequest('GET', $return->public_id, as: $this->owner)->assertOk()->assertJsonPath('data.status', 'completed');
    $this->store->run(function () use ($order): void {
        expect(ReturnRefund::query()->orderBy('attempt')->pluck('status')->map->value->all())->toBe(['succeeded', 'succeeded'])
            ->and(PaymentRefund::query()->where('status', RefundStatus::Succeeded->value)->count())->toBe(2)
            ->and(Order::query()->whereKey($order->id)->sole()->refunded_beyond_paid)->toBeTrue()
            ->and(OrderReturn::query()->sole()->statusChanges()->where('to_status', 'completed')->count())->toBe(1);
        Event::assertDispatchedTimes(ReturnCompleted::class, 1);
        Event::assertDispatched(OrderRefundedBeyondPaid::class);
        Event::assertDispatched(RefundSucceededAfterFailing::class);
    });
});

it('reads a refund split across payments that partly went back and partly failed as mixed, which is never tried again whole', function (): void {
    $refund = static fn (array $parts): Refund => (new Refund)->setRelation('paymentRefunds', collect(array_map(
        static fn (array $part): PaymentRefund => (new PaymentRefund)->forceFill(['status' => $part[0], 'needs_review_at' => $part[1] ? now() : null]),
        $parts,
    )));

    expect(ReturnRefundAttempts::outcome($refund([[RefundStatus::Succeeded, false], [RefundStatus::Failed, false]])))->toBe(ReturnRefundStatus::Mixed)
        ->and(ReturnRefundAttempts::outcome($refund([[RefundStatus::Succeeded, false], [RefundStatus::Succeeded, false]])))->toBe(ReturnRefundStatus::Succeeded)
        ->and(ReturnRefundAttempts::outcome($refund([[RefundStatus::Failed, false], [RefundStatus::Failed, false]])))->toBe(ReturnRefundStatus::Failed)
        ->and(ReturnRefundAttempts::outcome($refund([[RefundStatus::Succeeded, false], [RefundStatus::Pending, false]])))->toBe(ReturnRefundStatus::Pending)
        ->and(ReturnRefundAttempts::outcome($refund([[RefundStatus::Failed, false], [RefundStatus::Pending, true]])))->toBe(ReturnRefundStatus::InReview)
        ->and(array_map(static fn (ReturnRefundStatus $status): bool => $status->allowsNewAttempt(), ReturnRefundStatus::cases()))->toBe([false, true, false, false, false, true, false]);
});

it('saves Payments\' refusal on the attempt and announces no refund; a refused attempt can be followed by a new one', function (): void {
    Event::fake([ReturnRefundStarted::class]);
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    // Most of the payment was already given back by amount.
    refundOrder($order, $order->total->minorAmount() - 1000);

    refundReturnAs($return, $this->owner)->assertUnprocessable()->assertJsonPath('code', 'refund_exceeds_refundable');
    refundReturnAs($return, $this->owner)->assertUnprocessable();

    $this->store->run(static function (): void {
        expect(ReturnRefund::query()->orderBy('attempt')->get()->map(static fn (ReturnRefund $attempt): array => [$attempt->attempt, $attempt->status->value, $attempt->refusal_code])->all())->toBe([
            [1, 'refused', 'refund_exceeds_refundable'],
            [2, 'refused', 'refund_exceeds_refundable'],
        ])
            ->and(OrderReturn::query()->sole()->status->value)->toBe('inspected');
        Event::assertNotDispatched(ReturnRefundStarted::class);
    });
});

it('refuses the part a module\'s payment method holds, such as a gift card, with its own error', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    // The gateway's payment can give back only part of the return; a gift card holds money paid for the order.
    refundOrder($order, $order->total->minorAmount() - 5000);
    $this->store->run(static fn () => (new CheckoutPaymentHold)->forceFill([
        'public_id' => (string) Str::ulid(), 'order_id' => $order->id, 'method' => 'gift_card', 'reference' => 'gc_1', 'currency' => $order->currency,
        'amount' => Money::ofMinor(5000, $order->currency), 'status' => 'committed', 'committed_at' => now(),
    ])->save());

    refundReturnAs($return, $this->owner)->assertConflict()->assertJsonPath('code', 'return_refund_module_held');

    $this->store->run(static fn () => expect(ReturnRefund::query()->sole()->refusal_code)->toBe('return_refund_module_held'));
});

it('queues two refunds of the same return on its lock, so only one refund is made', function (): void {
    $return = inspectedReturn(returnableOrder([[10000, 1]], $this->customer, paidByGateway: true));

    $this->store->run(function () use ($return): void {
        $refund = fn () => app(RefundReturn::class)->handle($return, $this->owner);

        // Another request holds the return and has saved its first attempt, not yet committed.
        $otherRequest = SecondConnection::open()->holdRowLock(OrderReturn::query()->whereKey($return->id)->sole());
        $otherRequest->run(fn () => DB::connection(SecondConnection::NAME)->table('return_refunds')->insert([
            'return_id' => $return->id, 'attempt' => 1, 'idempotency_key' => "return:{$return->public_id}:1", 'status' => 'started', 'shipping' => false,
            'requested_by_type' => 'staff_member', 'requested_by_id' => $this->owner->public_id, 'created_at' => now(), 'updated_at' => now(),
        ]));

        expect($otherRequest->blockedStatement($refund))->toContain('"returns"')->toContain('for update');

        $otherRequest->commit();
        $refund();

        // Worked from the other request's attempt: asked Payments under its key, once.
        expect([ReturnRefund::query()->count(), Refund::query()->count(), Refund::query()->sole()->idempotency_key])->toBe([1, 1, "return:{$return->public_id}:1"]);
    });

    expect($this->script->timesCalled('refund'))->toBe(1);
});

it('waits on the order\'s lock inside Payments with its attempt already saved, so asking again sends the same refund once', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);

    $this->store->run(function () use ($return, $order): void {
        $refund = fn () => app(RefundReturn::class)->handle($return, $this->owner);

        // Another request, such as a direct refund, holds the order.
        $otherRequest = SecondConnection::open()->holdRowLock(Order::query()->whereKey($order->id)->sole());

        expect($otherRequest->blockedStatement($refund))->toContain('"orders"')->toContain('for update');

        // The attempt was committed before Payments was asked, outside any of Returns' transactions.
        expect(ReturnRefund::query()->sole()->status)->toBe(ReturnRefundStatus::Started);

        $otherRequest->commit();
        $refund();

        expect([ReturnRefund::query()->count(), Refund::query()->sole()->idempotency_key, OrderReturn::query()->sole()->status->value])->toBe([1, "return:{$return->public_id}:1", 'completed']);
    });
});

it('gives a unit back once when a return\'s refund and a direct refund of the same unit are sent at the same moment', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByHand: true);
    $return = inspectedReturn($order);
    $line = $order->lines[0]->public_id;

    // Two staff requests, each from a process and connection of its own.
    [$returnRefund, $directRefund] = requestsAtOnce([
        returnRefundRequest($return, staffTokenFor($this->owner, 'first-store')),
        orderRefundRequest($order, staffTokenFor($this->owner, 'first-store'), ['lines' => [['line' => $line, 'quantity' => 1]]]),
    ]);
    $returnWon = $returnRefund['status'] === 200;
    // Exactly one gave it back; Payments refused the other.
    expect([$returnRefund['status'], $directRefund['status']])->toBe($returnWon ? [200, 422] : [422, 201]);

    $this->store->run(static function () use ($returnWon, $order): void {
        $attempt = ReturnRefund::query()->sole();

        expect([Refund::query()->count(), (int) RefundLine::query()->sum('quantity'), OrderReturn::query()->sole()->status->value, $attempt->status])
            ->toBe([1, 1, $returnWon ? 'completed' : 'inspected', $returnWon ? ReturnRefundStatus::Succeeded : ReturnRefundStatus::Refused])
            ->and((int) PaymentRefund::query()->whereIn('status', RefundStatus::holdingMoney())->sum('amount_amount'))->toBeLessThanOrEqual($order->total->minorAmount());
    });
});

it('never gives back more than was paid when a return\'s refund and a refund by amount are sent at the same moment', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByHand: true);
    $return = inspectedReturn($order);
    // With the return's ₦100, more than was paid.
    $byAmount = $order->total->minorAmount() - 5000;

    [$returnRefund, $amountRefund] = requestsAtOnce([
        returnRefundRequest($return, staffTokenFor($this->owner, 'first-store')),
        orderRefundRequest($order, staffTokenFor($this->owner, 'first-store'), ['amount' => $byAmount]),
    ]);
    $returnWon = $returnRefund['status'] === 200;

    expect([$returnRefund['status'], $amountRefund['status']])->toBe($returnWon ? [200, 422] : [422, 201]);

    $this->store->run(static function () use ($returnWon, $byAmount): void {
        $given = (int) PaymentRefund::query()->whereIn('status', RefundStatus::holdingMoney())->sum('amount_amount');

        expect($given)->toBe($returnWon ? 10000 : $byAmount)
            ->toBeLessThanOrEqual(Payment::query()->sole()->received?->minorAmount() ?? 0)
            ->and(Refund::query()->count())->toBe(1);
    });
});
