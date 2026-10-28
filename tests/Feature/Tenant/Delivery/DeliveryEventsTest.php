<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Delivery\Events\DeliveryCancelled;
use App\Tenant\Delivery\Events\DeliveryCompleted;
use App\Tenant\Delivery\Events\DeliveryFailed;
use App\Tenant\Delivery\Events\DeliveryOnTheWay;
use App\Tenant\Delivery\Events\DeliveryPickedUp;
use App\Tenant\Delivery\Events\DriverAssigned;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 17: Delivery announces each change of a delivery once it is saved, for
 * Messaging (Step 20) to tell the buyer and the store: assigned, picked up
 * (also when sent out again), on the way, completed, failed and cancelled.
 */
uses(TruncatesCentralDatabase::class);

const DELIVERY_EVENTS = [DriverAssigned::class, DeliveryPickedUp::class, DeliveryOnTheWay::class, DeliveryCompleted::class, DeliveryFailed::class, DeliveryCancelled::class];

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['store' => $this->store, 'owner' => $this->owner, 'driver' => $this->driver, 'token' => $this->token] = deliveryStore();
});

afterEach(function (): void {
    deleteAllStores();
});

it('announces each change of a delivery, for the delivery it happened to', function (): void {
    Event::fake(DELIVERY_EVENTS);

    $delivered = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$delivered}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$delivered}/on-the-way", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$delivered}/delivered", ['recipient_name' => 'Ada Obi', 'photo' => proofPhoto()], $this->token, headers: ['Idempotency-Key' => 'delivered-events'])->assertOk();

    $failed = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$failed}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$failed}/failed", ['reason' => 'refused'], $this->token)->assertOk();
    $retry = deliveryRequest('POST', "deliveries/{$failed}/retry", as: $this->owner)->assertCreated()->json('data.id');

    $cancelled = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    deliveryRequest('POST', "deliveries/{$cancelled}/cancel", as: $this->owner)->assertOk();

    $for = static fn (string $event): array => Event::dispatched($event)->map(static fn (array $call): string => $call[0]->delivery->public_id)->values()->all();

    expect($for(DriverAssigned::class))->toBe([$delivered, $failed, $cancelled])
        // The retry starts picked up: the driver still has the goods.
        ->and($for(DeliveryPickedUp::class))->toBe([$delivered, $failed, $retry])
        ->and($for(DeliveryOnTheWay::class))->toBe([$delivered])
        ->and($for(DeliveryCompleted::class))->toBe([$delivered])
        ->and($for(DeliveryFailed::class))->toBe([$failed])
        ->and($for(DeliveryCancelled::class))->toBe([$cancelled]);
});

it('announces nothing for a change that is refused', function (): void {
    $delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    Event::fake(DELIVERY_EVENTS);

    driverAppRequest('POST', "deliveries/{$delivery}/on-the-way", token: $this->token)->assertConflict();
    driverAppRequest('POST', "deliveries/{$delivery}/delivered", ['recipient_name' => 'Ada Obi', 'photo' => proofPhoto()], $this->token, headers: ['Idempotency-Key' => 'delivered-refused'])->assertConflict();

    Event::assertNothingDispatched();
});
