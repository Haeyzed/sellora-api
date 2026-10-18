<?php

declare(strict_types=1);

use App\Landlord\Integrations\Models\WebhookEndpoint;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Shared\Integrations\Contracts\WebhookEndpoints;
use App\Tenant\Payments\Actions\CheckPendingPayment;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Events\PaymentUnresolved;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\ProcessPaymentWebhook;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentEvent;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15.3: checks with the gateway. Each pending gateway payment is asked
 * about on its own schedule, without waiting for a webhook, and becomes
 * unresolved (staff are told, still in motion) once it has been pending too
 * long. An hourly sweep is the safety net, and prunes webhook endpoints left
 * behind. Before an order expires, its payment under way is asked about one
 * last time. All of it carries on whatever the plan or the store's status.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class, ProcessPaymentWebhook::class]);
    $this->freezeTime();
    $this->payment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));
    // Runs one check, as the queue would, in the store.
    $this->check = fn (?int $checkNumber = null): Payment => $this->store->run(fn (): Payment => app(CheckPendingPayment::class)->handle($this->payment->refresh(), $checkNumber)->refresh());
});

afterEach(function (): void {
    deleteAllStores();
});

it('schedules a check with the gateway on the payments queue as soon as a payment starts', function (): void {
    Queue::assertPushedOn('payments', CheckPaymentWithGateway::class, fn (CheckPaymentWithGateway $job): bool => $job->paymentId === $this->payment->id
        && $job->checkNumber === 0
        && $job->delay instanceof DateTimeInterface
        && $job->delay->getTimestamp() === now()->addMinutes(2)->getTimestamp());
});

it('asks again after each wait while the payment is pending, until its schedule runs out', function (): void {
    foreach (range(0, 6) as $checkNumber) {
        ($this->check)($checkNumber);
    }

    expect(queuedChecks($this->payment))->toBe([0, 1, 2, 3, 4, 5, 6])
        ->and($this->script->timesCalled('verifyPayment', $this->payment->reference))->toBe(7);
    Queue::assertPushed(CheckPaymentWithGateway::class, static fn (CheckPaymentWithGateway $job): bool => $job->checkNumber === 6 && $job->delay instanceof DateTimeInterface && $job->delay->getTimestamp() === now()->addMinutes(720)->getTimestamp());
});

it('stops checking once the gateway settles the payment', function (): void {
    gatewayAnswers($this->payment, GatewayPaymentState::Succeeded, $this->payment->amount, GatewayMode::Live);

    expect(($this->check)(0)->status->value)->toBe('succeeded')
        ->and($this->store->run(fn (): string => $this->payment->order->refresh()->payment_status->value))->toBe('paid')
        ->and(queuedChecks($this->payment))->toBe([0]);

    ($this->check)(1);
    expect($this->script->timesCalled('verifyPayment'))->toBe(1);
});

it('never asks again on schedule about a payment that failed', function (): void {
    gatewayAnswers($this->payment, GatewayPaymentState::Failed);

    expect(($this->check)(0)->status->value)->toBe('failed');
    ($this->check)(1);

    expect($this->script->timesCalled('verifyPayment'))->toBe(1)
        ->and(queuedChecks($this->payment))->toBe([0]);
});

it('gives up on a payment still pending after a day: it becomes unresolved, staff are told, and money arriving later still counts', function (): void {
    Event::fake([PaymentUnresolved::class]);
    $this->travel(23)->hours();
    expect(($this->check)()->status->value)->toBe('pending');

    $this->travel(1)->hours();
    $unresolved = ($this->check)(3);

    expect($unresolved->status->value)->toBe('unresolved')
        ->and($unresolved->unresolved_at?->getTimestamp())->toBe(now()->getTimestamp())
        ->and(queuedChecks($this->payment))->toBe([0])
        ->and($this->store->run(fn (): array => $this->payment->events()->get()->map(static fn (PaymentEvent $event): string => $event->type->value)->all()))->toBe(['started', 'unresolved']);
    Event::assertDispatched(PaymentUnresolved::class, fn (PaymentUnresolved $event): bool => $event->payment->is($this->payment));

    // Still in motion: its gateway stays connected, and the gateway is still asked.
    staffOrderRequest('DELETE', '/payment-gateways/fake_gateway/connection')->assertConflict();
    gatewayAnswers($this->payment, GatewayPaymentState::Succeeded, $this->payment->amount, GatewayMode::Live);
    expect(($this->check)()->status->value)->toBe('succeeded');
});

it('lets only gateway payments become unresolved, and always records when', function (): void {
    $this->store->run(function (): void {
        $row = fn (): Illuminate\Database\Query\Builder => DB::table('payments')->where('id', $this->payment->id);
        DB::table('payments')->insert(['public_id' => (string) Str::ulid(), 'order_id' => $this->payment->order_id, 'method' => 'bank_transfer', 'is_test' => false, 'status' => 'pending', 'currency' => 'NGN', 'amount_amount' => 100, 'reference' => 'pay_bank', 'created_at' => now(), 'updated_at' => now()]);

        expect(static fn () => $row()->update(['status' => 'unresolved']))->toThrow(QueryException::class, 'payments_unresolved_at')
            ->and(static fn () => DB::table('payments')->where('reference', 'pay_bank')->update(['status' => 'unresolved', 'unresolved_at' => now()]))->toThrow(QueryException::class, 'payments_unresolved_only_for_gateway');
    });
});

it('sweeps up every hour what each payment\'s own checks missed, and webhooks left unhandled', function (): void {
    // Pending, never asked, started over an hour ago: swept.
    $never = $this->payment;
    $this->travel(61)->minutes();
    $expected = $this->store->run(static function () use ($never): array {
        $set = static fn (Payment $payment, array $values) => DB::table('payments')->where('id', $payment->id)->update($values);
        // Pending, quiet for over an hour: swept.
        $set($quiet = startFakePayment(unpaidOrder()), ['last_checked_at' => now()->subMinutes(61)]);
        // Pending, asked ten minutes ago: left to its own schedule.
        $set(startFakePayment(unpaidOrder()), ['last_checked_at' => now()->subMinutes(10)]);
        // Unresolved, last asked over a day ago: swept.
        $set($stale = startFakePayment(unpaidOrder()), ['status' => 'unresolved', 'unresolved_at' => now(), 'last_checked_at' => now()->subHours(25)]);
        // Unresolved, asked two hours ago: left.
        $set(startFakePayment(unpaidOrder()), ['status' => 'unresolved', 'unresolved_at' => now(), 'last_checked_at' => now()->subHours(2)]);

        return [[$never->id, null], [$quiet->id, null], [$stale->id, null]];
    });
    sendFakeWebhook(['id' => 'evt_old', 'type' => 'ignored'])->assertOk();
    sendFakeWebhook(['id' => 'evt_new', 'type' => 'ignored'])->assertOk();
    $this->store->run(static fn (): int => PaymentWebhookEvent::query()->where('event_id', 'evt_old')->update(['received_at' => now()->subMinutes(61)]));
    Queue::fake([CheckPaymentWithGateway::class, ProcessPaymentWebhook::class]);

    $this->artisan('payments:check-pending')->assertSuccessful();

    $swept = Queue::pushed(CheckPaymentWithGateway::class)->map(static fn (CheckPaymentWithGateway $job): array => [$job->paymentId, $job->checkNumber])->sort()->values()->all();
    expect($swept)->toBe($expected);
    Queue::assertPushedTimes(ProcessPaymentWebhook::class, 1);
    Queue::assertPushedOn('payments', ProcessPaymentWebhook::class);
});

it('deletes webhook endpoints no connection uses once they are an hour old, and keeps those that may still be saving', function (): void {
    $storeId = (string) $this->store->getTenantKey();
    $inUse = fakeWebhookEndpoint();
    $old = app(WebhookEndpoints::class)->create($storeId, 'fake_gateway');
    $young = app(WebhookEndpoints::class)->create($storeId, 'fake_gateway');
    WebhookEndpoint::query()->whereIn('public_id', [$old, $inUse])->update(['created_at' => now()->subMinutes(61)]);

    $this->artisan('payments:check-pending')->assertSuccessful();

    expect(WebhookEndpoint::query()->orderBy('public_id')->pluck('public_id')->all())->toBe(collect([$inUse, $young])->sort()->values()->all());
});

it('asks the gateway once more before an order expires, so a payment that went through pays it instead', function (): void {
    gatewayAnswers($this->payment, GatewayPaymentState::Succeeded, $this->payment->amount, GatewayMode::Live);
    $this->travel(61)->minutes();

    $this->artisan('inventory:release-expired-reservations')->assertSuccessful();

    $this->store->run(function (): void {
        expect($this->payment->order->refresh()->only(['status', 'payment_status']))->toMatchArray(['status' => App\Tenant\Orders\Enums\OrderStatus::Open, 'payment_status' => App\Tenant\Orders\Enums\OrderPaymentStatus::Paid])
            ->and($this->script->timesCalled('verifyPayment', $this->payment->reference))->toBe(1);
    });
});

it('lets an order expire when the gateway still has no answer, and owes back money that arrives afterwards', function (bool $isGatewayDown): void {
    $this->script->unavailable = $isGatewayDown;
    $this->travel(61)->minutes();

    $this->artisan('inventory:release-expired-reservations')->assertSuccessful();
    expect($this->store->run(fn (): string => $this->payment->order->refresh()->status->value))->toBe('cancelled');

    $this->script->unavailable = false;
    gatewayAnswers($this->payment, GatewayPaymentState::Succeeded, $this->payment->amount, GatewayMode::Live);
    ($this->check)();

    expect($this->store->run(fn (): array => $this->payment->order->refresh()->only(['status', 'refund_due'])))->toMatchArray(['refund_due' => true]);
})->with(['the gateway says pending' => false, 'the gateway is down' => true]);

it('keeps checking payments under way whatever the plan, and while the store is suspended', function (string $state): void {
    $state === 'store suspended' ? $this->store->update(['status' => TenantStatus::Suspended]) : putFakeGatewayInState($state);
    gatewayAnswers($this->payment, GatewayPaymentState::Succeeded, $this->payment->amount, GatewayMode::Live);
    $this->travel(61)->minutes();
    Queue::fake([CheckPaymentWithGateway::class]);

    $this->artisan('payments:check-pending')->assertSuccessful();
    $job = Queue::pushed(CheckPaymentWithGateway::class)->sole();
    $this->store->run(static fn () => $job->handle(app(CheckPendingPayment::class)));

    expect($this->store->run(fn (): string => $this->payment->order->refresh()->payment_status->value))->toBe('paid');
})->with(['locked', 'disabled', 'suspended', 'store suspended']);
