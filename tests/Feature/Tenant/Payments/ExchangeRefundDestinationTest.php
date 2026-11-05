<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Payments\Contracts\RefundDestination;
use App\Tenant\Payments\Data\DestinationRefund;
use App\Tenant\Payments\RefundDestinations;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18c: core's "exchange" destination takes the part of a return that
 * pays for its replacement order. The money stays in the store, so it is
 * paid out at once, never fails, still reduces the sale (a credit note), and
 * works for a guest's order too. Nobody can choose it, and no module can
 * take its key.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    returnsStore();
});

afterEach(function (): void {
    deleteAllStores();
});

it('pays a refund to the exchange at once, with a credit note, even for a guest\'s order', function (): void {
    $order = returnableOrder([[10000, 1]], paidByHand: true);
    $refund = refundToDestination($order, [$order->lines->sole()->public_id => 1], RefundDestinations::EXCHANGE);

    $credited = $this->store->run(static fn (): bool => CreditNote::query()->where('order_id', $order->id)->exists());

    expect($order->customer_id)->toBeNull()
        ->and($refund->paymentRefunds->sole()->status->value)->toBe('succeeded')
        ->and($refund->destination)->toBe('exchange')
        ->and($refund->paymentRefunds->sole()->destination_reference)->toBe('exchange:'.$refund->public_id)
        ->and($credited)->toBeTrue();
});

it('never offers the exchange, so a customer can\'t choose it', function (): void {
    $customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $return = requestedReturn(returnableOrder([[10000, 1]], customer: $customer));

    $offered = $this->store->run(static fn (): array => array_map(static fn ($destination): string => $destination->key, app(RefundDestinations::class)->offeredFor(App\Tenant\Orders\Models\Order::query()->findOrFail($return->order_id), $customer)));

    expect($offered)->not->toContain('exchange');

    chooseRefundDestinationAsCustomer($return, $customer, RefundDestinations::EXCHANGE)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'refund_destination_not_offered');
});

it('refuses a module destination that takes the exchange\'s key', function (): void {
    $destination = new class implements RefundDestination
    {
        public function key(): string
        {
            return RefundDestinations::EXCHANGE;
        }

        public function label(): string
        {
            return 'Exchange';
        }

        public function isOfferedFor(App\Tenant\Orders\Models\Order $order, Customer $customer): bool
        {
            return true;
        }

        public function giveBack(DestinationRefund $refund): string
        {
            return 'taken';
        }
    };
    app()->instance($destination::class, $destination);

    expect(fn () => app(RefundDestinations::class)->add($destination::class, 'wallet'))->toThrow(LogicException::class, 'are core\'s');
});
