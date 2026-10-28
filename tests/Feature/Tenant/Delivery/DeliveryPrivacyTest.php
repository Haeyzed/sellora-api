<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DriverLocation;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderAnonymisation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 10, Step 17: personal data in deliveries. A driver's export holds
 * their deliveries (never anything about the buyers), the status changes
 * they made and where they were; erasing them deletes their locations and
 * keeps the deliveries and cash ledger. When an order is anonymised (buyer
 * erased, guest erased, or retention), its deliveries lose who took the
 * goods, failure notes, proof and the driver's trail to the buyer, in the
 * same erasure run; proof files go once it commits.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['store' => $this->store, 'owner' => $this->owner, 'driver' => $this->driver, 'token' => $this->token] = deliveryStore();
    $this->delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$this->delivery}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$this->delivery}/on-the-way", token: $this->token)->assertOk();
    sendLocation($this->delivery, $this->token, 6.4541, 3.3947)->assertCreated();
});

afterEach(function (): void {
    deleteAllStores();
});

it('exports the driver\'s deliveries without the buyers\' data, the changes they made and where they were', function (): void {
    handOver(['photo' => proofPhoto()])->assertOk();

    $data = deliveryRequest('GET', "drivers/{$this->driver->public_id}/personal-data", as: $this->owner)->assertOk()->json('data');

    expect(array_keys($data))->toBe(['deliveries', 'locations', 'cash', 'account'])
        ->and($data['deliveries'])->toHaveCount(1)
        ->and($data['deliveries'][0])->toMatchArray(['id' => $this->delivery, 'status' => 'delivered'])
        ->and($data['deliveries'][0])->not->toHaveKeys(['recipient_name', 'failure_note', 'address', 'recipient'])
        ->and(array_column($data['deliveries'][0]['status_changes_by_driver'], 'to'))->toBe(['picked_up', 'on_the_way', 'delivered'])
        ->and($data['locations'])->toHaveCount(1)
        ->and($data['locations'][0])->toMatchArray(['delivery' => $this->delivery, 'latitude' => 6.4541, 'longitude' => 3.3947]);
});

it('deletes the driver\'s locations when they are erased, keeping their deliveries', function (): void {
    handOver(['photo' => proofPhoto()])->assertOk();

    deliveryRequest('POST', "drivers/{$this->driver->public_id}/erasure", ['password' => DELIVERY_STAFF_PASSWORD], $this->owner)->assertOk();

    expect($this->store->run(fn (): array => [DriverLocation::query()->count(), Delivery::query()->sole()->driver_id, Delivery::query()->sole()->recipient_name]))
        ->toBe([0, $this->driver->id, 'Ada Obi']);
});

it('clears who took the goods, failure notes, proof and the trail when the order is anonymised, keeping the delivery', function (): void {
    failWithNote();
    deliveryRequest('POST', "deliveries/{$this->delivery}/retry", as: $this->owner)->assertCreated();
    $retry = $this->store->run(static fn (): string => Delivery::query()->where('attempt', 2)->sole()->public_id);
    test()->delivery = $retry;
    sendLocation($retry, $this->token)->assertCreated();
    handOver(['photo' => proofPhoto(), 'signature' => proofPhoto('signature.png')])->assertOk();
    $files = privateStoreFiles();
    expect($files)->toHaveCount(2);

    $this->store->run(static fn () => app(PersonalDataErasure::class)->run(static fn () => app(OrderAnonymisation::class)->anonymise(Order::query()->sole())));

    $this->store->run(static function (): void {
        $deliveries = Delivery::query()->orderBy('attempt')->get();

        expect($deliveries->map(static fn (Delivery $delivery): array => [$delivery->status->value, $delivery->failure_reason?->value, $delivery->failure_note, $delivery->recipient_name, $delivery->anonymised_at !== null])->all())
            ->toBe([['failed', 'other', null, null, true], ['delivered', null, null, null, true]])
            ->and(DriverLocation::query()->count())->toBe(0)
            ->and(Media::query()->count())->toBe(0);
    });

    expect(privateStoreFiles())->toBe([]);
    deliveryRequest('GET', "deliveries/{$retry}", as: $this->owner)->assertOk()
        ->assertJsonPath('data.recipient_name', null)
        ->assertJsonPath('data.proof', ['photo' => false, 'signature' => false]);
});

it('keeps the proof files when the erasure run rolls back', function (): void {
    handOver(['photo' => proofPhoto()])->assertOk();

    $this->store->run(static function (): void {
        expect(static fn () => app(PersonalDataErasure::class)->run(static function (): void {
            app(OrderAnonymisation::class)->anonymise(Order::query()->sole());

            throw new RuntimeException('Something later in the erasure failed.');
        }))->toThrow(RuntimeException::class);

        expect(Delivery::query()->sole()->recipient_name)->toBe('Ada Obi')
            ->and(Media::query()->count())->toBe(1);
    });

    expect(privateStoreFiles())->toHaveCount(1);
    deliveryRequest('GET', "deliveries/{$this->delivery}/proof/photo", as: $this->owner)->assertOk();
});

it('refuses clearing who took the goods outside an anonymisation', function (): void {
    handOver(['photo' => proofPhoto()])->assertOk();

    expect(fn () => $this->store->run(static fn () => Delivery::query()->toBase()->update(['recipient_name' => null])))
        ->toThrow(Illuminate\Database\QueryException::class, 'deliveries_delivered');
});
