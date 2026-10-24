<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Payments\Actions\CheckPendingRefund;
use App\Tenant\Payments\Actions\SendRefundToGateway;
use App\Tenant\Payments\Actions\VerifyRefund;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Events\RefundNeedsReview;
use App\Tenant\Payments\Http\Resources\RefundResource;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16b.3: refunds are never sent blindly, whatever the gateway. Each send
 * is claimed first in one conditional update, so two callers at once never
 * both reach the gateway. A refund whose sending went unanswered is sent
 * again only when the gateway twice has none, at least
 * payments.checks.refund_resend_after_minutes apart, and only once; the
 * database refuses a third send. Whatever the gateway's answers can't settle
 * goes to staff review, once, and is never sent again.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    // Each refund's own checks are run by hand.
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
    [$this->order] = gatewayPaidOrder();
});

afterEach(function (): void {
    deleteAllStores();
});

it('claims each send first, so a refund already sent, or sent again, is never sent once more by another caller', function (): void {
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();

    $this->store->run(static function () use ($part): void {
        app(SendRefundToGateway::class)->handle($part->refresh());
        app(SendRefundToGateway::class)->resend($part->refresh());
        app(SendRefundToGateway::class)->resend($part->refresh());
        app(SendRefundToGateway::class)->handle($part->refresh());
    });

    expect([$this->script->timesCalled('refund', $part->reference), $this->store->run(static fn (): int => $part->refresh()->send_attempts)])->toBe([2, 2]);
});

it('never sends a refund a third time, never takes a send back, and never takes a refund out of review', function (Closure $change): void {
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();

    expect(fn () => $this->store->run(static fn () => DB::table('payment_refunds')->where('id', $part->id)->update($change())))->toThrow(QueryException::class);
})->with([
    'a third send' => [static fn (): array => ['send_attempts' => 3]],
    'a send taken back' => [static fn (): array => ['send_attempts' => 0]],
    'a review without its reason' => [static fn (): array => ['needs_review_at' => now()]],
]);

it('keeps a refund in review there', function (): void {
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();
    $this->store->run(static fn () => DB::table('payment_refunds')->where('id', $part->id)->update(['needs_review_at' => now(), 'review_reason' => 'gateway_unclear']));

    expect(fn () => $this->store->run(static fn () => DB::table('payment_refunds')->where('id', $part->id)->update(['needs_review_at' => null, 'review_reason' => null])))->toThrow(QueryException::class)
        ->and(fn () => $this->store->run(static fn () => DB::table('payment_refunds')->where('id', $part->id)->update(['review_reason' => 'lookup_failed'])))->toThrow(QueryException::class);
});

it('waits as long as configured between the two answers that a refund was never received, and not a moment longer', function (): void {
    // Time moves only when the test moves it, so the wait ends exactly on the minute.
    $this->freezeSecond();
    config(['payments.checks.refund_resend_after_minutes' => 30]);
    $this->script->unavailable = true;
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();
    $this->script->unavailable = false;

    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    $this->travel(29)->minutes();
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));

    expect($this->script->timesCalled('refund'))->toBe(1);

    $this->travel(1)->minutes();
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));

    expect($this->script->timesCalled('refund'))->toBe(2);
});

it('sends to staff review, once, a refund whose sending went unanswered when the gateway can\'t then be asked, and never sends it again', function (): void {
    Event::fake([RefundNeedsReview::class]);
    $this->script->unavailable = true;
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();

    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    $this->script->unavailable = false;
    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    $this->travel(60)->minutes();
    $reviewed = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$reviewed->status->value, $reviewed->review_reason?->value, $this->script->timesCalled('refund')])->toBe(['pending', 'lookup_failed', 1])
        ->and($this->store->run(static fn (): array => (new RefundResource($reviewed->refund))->resolve()['parts'][0]))->toMatchArray(['status' => 'pending', 'review_reason' => 'lookup_failed', 'needs_review_at' => $reviewed->needs_review_at?->toIso8601String()]);
    Event::assertDispatchedTimes(RefundNeedsReview::class, 1);
});

it('sends to staff review a refund the gateway answers about unclearly', function (): void {
    Event::fake([RefundNeedsReview::class]);
    $this->script->unavailable = true;
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();
    $this->script->unavailable = false;
    $this->script->refunds[$part->reference] = new GatewayRefundResult(GatewayRefundState::Unclear);

    $reviewed = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$reviewed->review_reason?->value, $this->script->timesCalled('refund')])->toBe(['gateway_unclear', 1]);
    Event::assertDispatched(RefundNeedsReview::class, static fn (RefundNeedsReview $event): bool => $event->paymentRefund->is($part));
});

it('asks about a refund by the gateway\'s own ID once it gave one, and never sends one it acknowledged again, even if later missing', function (): void {
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending, 'fake_r', Money::ofMinor(4000, 'NGN'));
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();
    $this->script->refunds[$part->reference] = new GatewayRefundResult(GatewayRefundState::NotFound);

    $this->store->run(static fn () => app(CheckPendingRefund::class)->handle($part->refresh()));
    $this->travel(60)->minutes();
    $checked = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$checked->status->value, $checked->needs_review_at, $this->script->timesCalled('refund')])->toBe(['pending', null, 1])
        ->and($this->script->refundLookups)->toBe([[$part->reference, 'fake_r'], [$part->reference, 'fake_r']]);

    $this->script->unavailable = true;
    $unanswered = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$unanswered->status->value, $unanswered->needs_review_at])->toBe(['pending', null]);
});

it('sends a refund whose first sending never began when it is first checked, and looks for none', function (): void {
    $part = $this->store->run(static function (): PaymentRefund {
        $payment = Payment::query()->sole();
        $refundId = DB::table('refunds')->insertGetId(['public_id' => (string) Str::ulid(), 'order_id' => $payment->order_id, 'currency' => 'NGN', 'amount_amount' => 4000, 'purpose' => 'reduces_sale', 'requested_by_type' => 'staff', 'requested_by_id' => 'staff_1', 'created_at' => now()]);
        DB::table('payment_refunds')->insert(['public_id' => (string) Str::ulid(), 'refund_id' => $refundId, 'payment_id' => $payment->id, 'currency' => 'NGN', 'amount_amount' => 4000, 'reference' => 'rfd_never_sent', 'created_at' => now(), 'updated_at' => now()]);

        return PaymentRefund::query()->where('reference', 'rfd_never_sent')->sole();
    });

    $checked = $this->store->run(static fn (): PaymentRefund => app(CheckPendingRefund::class)->handle($part->refresh())->refresh());

    expect([$checked->status->value, $checked->send_attempts, $this->script->timesCalled('refund', 'rfd_never_sent'), $this->script->refundLookups])->toBe(['succeeded', 1, 1, []]);
});

it('never sends again a refund the gateway refused, whatever later checks find', function (): void {
    $this->script->rejectsRefunds = true;
    $part = refundOrder($this->order, 4000)->paymentRefunds->sole();
    $this->script->rejectsRefunds = false;

    $this->store->run(static fn () => app(VerifyRefund::class)->handle($part->refresh()));
    $this->travel(60)->minutes();
    $this->store->run(static fn () => app(VerifyRefund::class)->handle($part->refresh()));
    $this->script->unavailable = true;
    $checked = $this->store->run(static fn (): PaymentRefund => app(VerifyRefund::class)->handle($part->refresh())->refresh());

    expect([$checked->status->value, $checked->needs_review_at, $this->script->timesCalled('refund')])->toBe(['failed', null, 1]);
});
