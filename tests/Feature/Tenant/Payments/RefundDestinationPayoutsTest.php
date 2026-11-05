<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Money;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Payments\Actions\CheckPendingRefund;
use App\Tenant\Payments\Actions\GiveRefundToDestination;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Actions\SendRefundToGateway;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\RefundPurpose;
use App\Tenant\Payments\Events\RefundDestinationFailed;
use App\Tenant\Payments\Events\RefundGivenToDestination;
use App\Tenant\Payments\Exceptions\RefundDestinationRefusedException;
use App\Tenant\Payments\Exceptions\RefundExceedsRefundableException;
use App\Tenant\Payments\Exceptions\RefundInvalidException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Jobs\GiveRefundToDestinationAgain;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\PendingPaymentSweep;
use App\Tenant\Payments\RefundDestinations;
use App\Tenant\Payments\Services\MoneyInMotion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakeStoreCreditDestination;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18b: a refund to a module's destination the customer chose (section
 * 13), such as store credit. It draws its parts from the order's payments,
 * so what each can give back stays exact, but no gateway is ever asked: the
 * module pays it out, once however often it is asked, and outside any
 * transaction. A definite refusal fails it (and the customer can choose
 * again); an unknown outcome is asked again, then goes to staff review; a
 * destination no module registers, or one unavailable to the store, is a
 * definite failure, never a crash. Paid out in every module state but
 * Unavailable. It reduces the sale, so it gets a credit note.
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
    useStoreCreditDestination();
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class, GiveRefundToDestinationAgain::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('pays a refund out to the destination, once and outside any transaction, never through the gateway, with its credit note', function (): void {
    Event::fake([RefundGivenToDestination::class]);
    $order = returnableOrder([[10000, 2]], $this->customer, paidByGateway: true);

    $refund = refundToDestination($order, [$order->lines[0]->public_id => 1]);

    expect(FakeStoreCreditDestination::$asked)->toBe([[
        'refund' => $refund->public_id, 'order' => $order->public_id, 'customer' => $this->customer->public_id,
        'amount' => 10000, 'currency' => 'NGN', 'is_test' => false, 'transaction_level' => 0,
    ]])
        ->and($this->script->timesCalled('refund'))->toBe(0)
        ->and($refund->destination)->toBe(FakeStoreCreditDestination::KEY)
        ->and($refund->purpose)->toBe(RefundPurpose::ReducesSale)
        ->and($refund->paymentRefunds->map(static fn (PaymentRefund $part): array => [$part->status->value, $part->destination_reference, $part->recorded_by_type])->all())
        ->toBe([['succeeded', 'sc_'.$refund->public_id, null]]);

    $this->store->run(function () use ($order): void {
        expect(CreditNote::query()->sole()->total->minorAmount())->toBe(10000)
            ->and($order->refresh()->payment_status->value)->toBe('partially_refunded');
        Event::assertDispatchedTimes(RefundGivenToDestination::class, 1);

        // What went to store credit is no longer there to give back through the gateway.
        $left = $order->total->subtract(Money::ofMinor(10000, 'NGN'));
        expect(static fn () => app(RefundOrder::class)->handle(StaffMember::query()->firstOrFail(), $order, new RefundRequest(amount: $left->add(Money::ofMinor(1, 'NGN')), reason: 'Too much')))
            ->toThrow(RefundExceedsRefundableException::class);
    });
});

it('fails a refund the destination definitely refused, releasing what the payment can give back, and never tries the gateway', function (): void {
    Event::fake([RefundDestinationFailed::class]);
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    FakeStoreCreditDestination::$failure = new RefundDestinationRefusedException;

    $refund = refundToDestination($order, [$order->lines[0]->public_id => 1]);

    expect($refund->paymentRefunds->map(static fn (PaymentRefund $part): array => [$part->status->value, $part->failure_code])->all())->toBe([['failed', 'refund_destination_refused']])
        ->and($this->script->timesCalled('refund'))->toBe(0);
    $this->store->run(static fn () => Event::assertDispatched(RefundDestinationFailed::class, static fn (RefundDestinationFailed $event): bool => $event->failureCode === 'refund_destination_refused'));

    // Nothing went out, so the same units can still go back the way they were paid.
    $again = refundToDestination($order, [$order->lines[0]->public_id => 1], 'original_payment');
    expect($again->paymentRefunds->sole()->status->value)->toBe('succeeded')
        ->and($this->script->timesCalled('refund'))->toBe(1);
});

it('asks again after an unknown outcome, and never asks the gateway about it', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    FakeStoreCreditDestination::$failure = new RuntimeException('The wallet service timed out.');

    $refund = refundToDestination($order, [$order->lines[0]->public_id => 1]);
    $part = $refund->paymentRefunds->sole();

    expect([$part->status->value, $part->checks, $part->needs_review_at])->toBe(['pending', 1, null]);
    Queue::assertPushed(GiveRefundToDestinationAgain::class, static fn (GiveRefundToDestinationAgain $job): bool => $job->refundId === $refund->id);

    // The gateway's own checks leave it alone.
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle(PaymentRefund::query()->sole()));
    expect($this->script->timesCalled('refund') + $this->script->timesCalled('verifyRefund'))->toBe(0);

    // Nor is it ever sent to the gateway, or kept as the gateway's money in motion.
    $this->store->run(static function (): void {
        $part = PaymentRefund::query()->sole();
        app(SendRefundToGateway::class)->handle($part);

        expect([$part->refresh()->send_attempts, app(MoneyInMotion::class)->on($part->payment->connection ?? throw new LogicException('Paid through the gateway.'))])->toBe([0, false]);
    });
    expect($this->script->timesCalled('refund'))->toBe(0);

    $this->travel(5)->minutes();
    FakeStoreCreditDestination::$failure = null;
    $this->store->run(static fn () => app(GiveRefundToDestination::class)->handle(Refund::query()->sole()));

    expect(soleRefundPart()->status->value)->toBe('succeeded')
        ->and(FakeStoreCreditDestination::$asked)->toHaveCount(2)
        ->and(FakeStoreCreditDestination::$paid)->toBe([$refund->public_id => 'sc_'.$refund->public_id]);
});

it('sends a refund to staff review once the waits run out without an answer, and never asks again', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    FakeStoreCreditDestination::$failure = new RuntimeException('The wallet service timed out.');
    refundToDestination($order, [$order->lines[0]->public_id => 1]);

    foreach (config()->array('payments.destinations.retry_minutes') as $wait) {
        $this->travel((int) $wait)->minutes();
        $this->store->run(static fn () => app(GiveRefundToDestination::class)->handle(Refund::query()->sole()));
    }

    $part = soleRefundPart();
    expect([$part->status->value, $part->review_reason?->value, $part->needs_review_at !== null])->toBe(['pending', 'destination_unclear', true])
        ->and(FakeStoreCreditDestination::$asked)->toHaveCount(5);

    $this->travel(1)->day();
    $this->store->run(static fn () => app(GiveRefundToDestination::class)->handle(Refund::query()->sole()));
    expect(FakeStoreCreditDestination::$asked)->toHaveCount(5)
        ->and($this->script->timesCalled('refund'))->toBe(0);
});

it('asks the destination once when two callers arrive at the same moment', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    FakeStoreCreditDestination::$whilePaying = static fn () => app(GiveRefundToDestination::class)->handle(Refund::query()->sole());

    refundToDestination($order, [$order->lines[0]->public_id => 1]);

    expect(FakeStoreCreditDestination::$asked)->toHaveCount(1)
        ->and(soleRefundPart()->status->value)->toBe('succeeded');
});

it('fails, without crashing, a refund to a destination no module registers any more', function (): void {
    Event::fake([RefundDestinationFailed::class]);
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    app()->instance(RefundDestinations::class, new RefundDestinations(app()));

    $refund = refundToDestination($order, [$order->lines[0]->public_id => 1]);

    expect($refund->paymentRefunds->map(static fn (PaymentRefund $part): array => [$part->status->value, $part->failure_code])->all())->toBe([['failed', 'destination_not_registered']])
        ->and(FakeStoreCreditDestination::$asked)->toBe([])
        ->and($this->script->timesCalled('refund'))->toBe(0);
    $this->store->run(static fn () => Event::assertDispatched(RefundDestinationFailed::class, static fn (RefundDestinationFailed $event): bool => $event->failureCode === 'destination_not_registered'));
});

it('pays out in every module state but unavailable: money in motion is never blocked', function (string $state, string $status, ?string $failureCode): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    putStoreCreditDestinationIn($state);

    $refund = refundToDestination($order, [$order->lines[0]->public_id => 1]);

    expect([$refund->paymentRefunds->sole()->status->value, $refund->paymentRefunds->sole()->failure_code])->toBe([$status, $failureCode]);
})->with([
    'locked by a downgrade' => ['locked', 'succeeded', null],
    'switched off by the merchant' => ['disabled', 'succeeded', null],
    'the store suspended' => ['suspended', 'succeeded', null],
    'unavailable' => ['unavailable', 'failed', 'destination_unavailable'],
]);

it('tells the destination when a refund belongs to a test order', function (): void {
    connectFakeGateway('test');
    $order = returnableOrder([[10000, 1]], $this->customer, isTest: true, paidByGateway: true);

    refundToDestination($order, [$order->lines[0]->public_id => 1]);

    expect(array_column(FakeStoreCreditDestination::$asked, 'is_test'))->toBe([true]);
});

it('refuses a destination for a guest\'s order, for one named payment, or for money that was never part of the sale', function (string $case): void {
    $order = returnableOrder([[10000, 1]], $case === 'guest' ? null : $this->customer, paidByGateway: true);

    $this->store->run(function () use ($order, $case): void {
        $request = match ($case) {
            'guest' => new RefundRequest(lines: [$order->lines[0]->public_id => 1], destination: FakeStoreCreditDestination::KEY),
            'named payment' => new RefundRequest(lines: [$order->lines[0]->public_id => 1], paymentId: Payment::query()->where('order_id', $order->id)->firstOrFail()->public_id, destination: FakeStoreCreditDestination::KEY),
            'overpayment' => new RefundRequest(amount: Money::ofMinor(100, 'NGN'), purpose: RefundPurpose::ReturnsPayment, destination: FakeStoreCreditDestination::KEY),
        };

        expect(static fn () => app(RefundOrder::class)->handle(StaffMember::query()->firstOrFail(), $order, $request))->toThrow(RefundInvalidException::class)
            ->and(Refund::query()->count())->toBe(0);
    });
})->with(['guest', 'named payment', 'overpayment']);

it('asks a destination again from the hourly sweep when its next ask was lost', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    FakeStoreCreditDestination::$failure = new RuntimeException('The wallet service timed out.');
    $refund = refundToDestination($order, [$order->lines[0]->public_id => 1]);

    $this->travel(config()->integer('payments.checks.sweep_after_minutes') + 1)->minutes();
    $this->store->run(static fn () => app(PendingPaymentSweep::class)->queueOverdueChecks());

    Queue::assertPushed(GiveRefundToDestinationAgain::class, 2);
    Queue::assertNotPushed(CheckRefundWithGateway::class, static fn (CheckRefundWithGateway $job): bool => $job->paymentRefundId === $refund->paymentRefunds->sole()->id);
});
