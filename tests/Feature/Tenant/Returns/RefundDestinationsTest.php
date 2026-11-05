<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Payments\Actions\FindRefundDestinations;
use App\Tenant\Payments\Contracts\RefundDestination;
use App\Tenant\Payments\Data\OfferedRefundDestination;
use App\Tenant\Payments\RefundDestinations;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakeStoreCreditDestination;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18b: where a refund can go (section 13). Back to the original
 * payment is core's own, always offered and the only one without modules. A
 * module's destination is offered only to the signed-in customer whose order
 * it is, only while the module is Enabled, and only when its own rules allow.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    returnsStore();
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
});

afterEach(function (): void {
    deleteAllStores();
});

it('offers only the original payment without modules', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);

    customerReturnRequest('GET', "orders/{$order->public_id}/refund-destinations", $this->customer)
        ->assertOk()
        ->assertExactJson(['data' => [['key' => 'original_payment', 'label' => 'Back to how you paid']]]);
});

it('offers a module\'s destination to the order\'s own customer, for the order and for its returns, only while the module is enabled', function (string $state, bool $isOffered): void {
    useStoreCreditDestination();
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    putStoreCreditDestinationIn($state);
    $expected = $isOffered ? ['original_payment', FakeStoreCreditDestination::KEY] : ['original_payment'];

    expect(customerReturnRequest('GET', "orders/{$order->public_id}/refund-destinations", $this->customer)->assertOk()->json('data.*.key'))->toBe($expected)
        ->and(customerReturnRequest('GET', "returns/{$return->public_id}/refund-destinations", $this->customer)->assertOk()->json('data.*.key'))->toBe($expected);
})->with([
    'enabled' => ['enabled', true],
    'locked by a downgrade' => ['locked', false],
    'switched off by the merchant' => ['disabled', false],
    'the store suspended' => ['suspended', false],
    'unavailable' => ['unavailable', false],
]);

it('names each destination in the customer\'s language', function (): void {
    useStoreCreditDestination();
    $order = returnableOrder([[10000, 1]], $this->customer);

    expect(customerReturnRequest('GET', "orders/{$order->public_id}/refund-destinations", $this->customer)->json('data'))
        ->toBe([['key' => 'original_payment', 'label' => 'Back to how you paid'], ['key' => FakeStoreCreditDestination::KEY, 'label' => 'Store credit']]);

    app()->setLocale('fr');
    expect($this->store->run(fn (): array => array_map(static fn (OfferedRefundDestination $offered): string => $offered->label, app(FindRefundDestinations::class)->handle($order, $this->customer))))
        ->toBe(['Sur le moyen de paiement utilisé', 'Avoir en boutique']);
});

it('leaves a module\'s destination out when its own rules don\'t offer it', function (): void {
    useStoreCreditDestination();
    FakeStoreCreditDestination::$isOffered = false;
    $order = returnableOrder([[10000, 1]], $this->customer);

    customerReturnRequest('GET', "orders/{$order->public_id}/refund-destinations", $this->customer)->assertOk()->assertJsonPath('data.*.key', ['original_payment']);
});

it('offers a guest, or anyone but the order\'s own customer, only the original payment', function (): void {
    useStoreCreditDestination();
    $guestOrder = returnableOrder([[10000, 1]]);
    $order = returnableOrder([[10000, 1]], $this->customer);
    $someoneElse = $this->store->run(static fn (): Customer => Customer::factory()->create());

    $this->store->run(function () use ($guestOrder, $order, $someoneElse): void {
        $keys = static fn (array $offered): array => array_map(static fn (OfferedRefundDestination $destination): string => $destination->key, $offered);
        $find = app(FindRefundDestinations::class);

        expect($keys($find->handle($guestOrder, null)))->toBe(['original_payment'])
            ->and($keys($find->handle($guestOrder, $this->customer)))->toBe(['original_payment'])
            ->and($keys($find->handle($order, $someoneElse)))->toBe(['original_payment'])
            ->and($keys($find->handle($order, $this->customer)))->toBe(['original_payment', FakeStoreCreditDestination::KEY]);
    });
});

it('never shows another customer\'s order or return', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    $someoneElse = $this->store->run(static fn (): Customer => Customer::factory()->create());

    customerReturnRequest('GET', "orders/{$order->public_id}/refund-destinations", $someoneElse)->assertNotFound();
    customerReturnRequest('GET', "returns/{$return->public_id}/refund-destinations", $someoneElse)->assertNotFound();
});

it('refuses a destination with a malformed key, core\'s key, or another\'s key', function (string $key): void {
    $destinations = new RefundDestinations(app());
    $destinations->add(FakeStoreCreditDestination::class, FakeStoreCreditDestination::FEATURE);
    app()->instance('test.refund-destination-key', $key);
    $clashing = new class implements RefundDestination
    {
        public function key(): string
        {
            return app('test.refund-destination-key');
        }

        public function label(): string
        {
            return 'Clashing';
        }

        public function isOfferedFor(App\Tenant\Orders\Models\Order $order, Customer $customer): bool
        {
            return true;
        }

        public function giveBack(App\Tenant\Payments\Data\DestinationRefund $refund): string
        {
            return 'never';
        }
    };

    expect(static fn () => $destinations->add($clashing::class, 'clashing_module'))->toThrow(LogicException::class);
})->with([
    'malformed' => ['Store-Credit'],
    'core\'s' => ['original_payment'],
    'another\'s' => [FakeStoreCreditDestination::KEY],
]);
