<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Money;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Payments\Actions\GiveRefundToDestination;
use App\Tenant\Payments\Exceptions\RefundDestinationRefusedException;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Jobs\GiveRefundToDestinationAgain;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\RefundDestinations;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefund;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakeStoreCreditDestination;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18b: a return's refund goes where the customer chose (section 13).
 * Each attempt saves the choice it follows. Once an attempt definitely got
 * nothing back (the destination clearly refused, or no module registers it
 * any more), the choice reopens and the next attempt follows the new one;
 * an unknown outcome keeps it closed. The choice only covers what was paid
 * with real money.
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

it('refunds a return to the destination the customer chose, completing it, with its credit note', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    chooseRefundDestinationAsCustomer($return, $this->customer, FakeStoreCreditDestination::KEY)->assertOk();

    refundReturnAs($return, $this->owner)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.refunds.0.destination', FakeStoreCreditDestination::KEY)
        ->assertJsonPath('data.refunds.0.status', 'succeeded');

    expect(array_column(FakeStoreCreditDestination::$asked, 'amount'))->toBe([10000])
        ->and($this->script->timesCalled('refund'))->toBe(0);
    $this->store->run(static fn () => expect([Refund::query()->sole()->destination, CreditNote::query()->sole()->total->minorAmount()])->toBe([FakeStoreCreditDestination::KEY, 10000]));
});

it('follows the choice staff recorded at the customer\'s request', function (): void {
    $return = inspectedReturn(returnableOrder([[10000, 1]], $this->customer, paidByGateway: true));
    returnsRequest('PUT', "{$return->public_id}/refund-destination", ['destination' => FakeStoreCreditDestination::KEY, 'note' => 'Asked in the shop'], as: $this->owner)->assertOk();

    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.refunds.0.destination', FakeStoreCreditDestination::KEY);

    expect(FakeStoreCreditDestination::$asked)->toHaveCount(1);
});

it('reopens the choice once the destination definitely refused, and the next attempt goes back to the original payment', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    chooseRefundDestinationAsCustomer($return, $this->customer, FakeStoreCreditDestination::KEY)->assertOk();
    FakeStoreCreditDestination::$failure = new RefundDestinationRefusedException;

    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.status', 'inspected')->assertJsonPath('data.refunds.0.status', 'failed');

    chooseRefundDestinationAsCustomer($return, $this->customer, 'original_payment')->assertOk();
    $response = refundReturnAs($return, $this->owner)->assertOk();

    expect($response->json('data.status'))->toBe('completed')
        ->and(array_map(static fn (array $attempt): array => [$attempt['attempt'], $attempt['status'], $attempt['destination']], $response->json('data.refunds')))
        ->toBe([[1, 'failed', FakeStoreCreditDestination::KEY], [2, 'succeeded', 'original_payment']])
        ->and($this->script->timesCalled('refund'))->toBe(1);
});

it('keeps the choice closed after an unknown outcome, and completes the return once the destination paid it out', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    chooseRefundDestinationAsCustomer($return, $this->customer, FakeStoreCreditDestination::KEY)->assertOk();
    FakeStoreCreditDestination::$failure = new RuntimeException('The wallet service timed out.');

    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.refunds.0.status', 'pending');

    // It may yet be paid out: no other choice, and no second attempt.
    chooseRefundDestinationAsCustomer($return, $this->customer, 'original_payment')->assertConflict()->assertJsonPath('code', 'refund_destination_choice_closed');
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonCount(1, 'data.refunds');

    FakeStoreCreditDestination::$failure = null;
    $this->travel(5)->minutes();
    $this->store->run(static fn () => app(GiveRefundToDestination::class)->handle(Refund::query()->sole()));

    $this->store->run(static fn () => expect([OrderReturn::query()->sole()->status->value, ReturnRefund::query()->sole()->status->value])->toBe(['completed', 'succeeded']));
    expect($this->script->timesCalled('refund'))->toBe(0);
});

it('counts a destination no module registers any more as a definite failure, reopening the choice', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    chooseRefundDestinationAsCustomer($return, $this->customer, FakeStoreCreditDestination::KEY)->assertOk();
    // The module is removed from the code.
    app()->instance(RefundDestinations::class, new RefundDestinations(app()));

    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.refunds.0.status', 'failed');
    $this->store->run(static fn () => expect(PaymentRefund::query()->sole()->failure_code)->toBe('destination_not_registered'));

    chooseRefundDestinationAsCustomer($return, $this->customer, 'original_payment')->assertOk();
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.refunds.1.destination', 'original_payment');
});

it('never sends what a module\'s payment method paid to the chosen destination', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, paidByGateway: true);
    $return = inspectedReturn($order);
    chooseRefundDestinationAsCustomer($return, $this->customer, FakeStoreCreditDestination::KEY)->assertOk();
    // The gateway's payment can give back only part of the return; a gift card holds money paid for the order.
    refundOrder($order, $order->total->minorAmount() - 5000);
    $this->store->run(static fn () => (new CheckoutPaymentHold)->forceFill([
        'public_id' => (string) Str::ulid(), 'order_id' => $order->id, 'method' => 'gift_card', 'reference' => 'gc_1', 'currency' => $order->currency,
        'amount' => Money::ofMinor(5000, $order->currency), 'status' => 'committed', 'committed_at' => now(),
    ])->save());

    refundReturnAs($return, $this->owner)->assertConflict()->assertJsonPath('code', 'return_refund_module_held');

    expect(FakeStoreCreditDestination::$asked)->toBe([]);
});
