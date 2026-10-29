<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Returns\Events\ReturnApproved;
use App\Tenant\Returns\Events\ReturnCancelled;
use App\Tenant\Returns\Events\ReturnInspected;
use App\Tenant\Returns\Events\ReturnReceived;
use App\Tenant\Returns\Events\ReturnRejected;
use App\Tenant\Returns\Events\ReturnRequested;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18 events (section 13): each change of a return is announced once,
 * for the right return, only after it commits, and never for a change that
 * was refused.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
});

afterEach(function (): void {
    deleteAllStores();
});

it('announces a request once each from a customer, a guest and staff, and never a refused one', function (): void {
    Event::fake([ReturnRequested::class]);
    $customerOrder = returnableOrder([[10000, 1]], $this->customer);
    $guestOrder = returnableOrder([[10000, 1]]);
    $guestToken = guestOrderToken($guestOrder);
    $staffOrder = returnableOrder([[10000, 1]], $this->customer);
    $payload = static fn ($order, int $units = 1): array => ['lines' => returnLinesPayload([$order->lines[0]->public_id => $units]), 'reason' => 'changed_mind'];

    customerReturnRequest('POST', "orders/{$customerOrder->public_id}/returns", $this->customer, $payload($customerOrder, 2))->assertUnprocessable();
    $this->store->run(static fn () => Event::assertNotDispatched(ReturnRequested::class));

    $ids = [
        customerReturnRequest('POST', "orders/{$customerOrder->public_id}/returns", $this->customer, $payload($customerOrder))->assertCreated()->json('data.id'),
        guestReturnRequest('POST', "{$guestOrder->public_id}/returns", $guestToken, $payload($guestOrder))->assertCreated()->json('data.id'),
        startReturnAsStaff($staffOrder, $payload($staffOrder), $this->owner)->assertCreated()->json('data.id'),
    ];

    $this->store->run(static function () use ($ids): void {
        Event::assertDispatchedTimes(ReturnRequested::class, 3);

        foreach ($ids as $id) {
            Event::assertDispatched(ReturnRequested::class, static fn (ReturnRequested $event): bool => $event->return->public_id === $id);
        }
    });
});

it('announces an approval once, and never a refused one', function (): void {
    Event::fake([ReturnApproved::class]);
    $return = requestedReturn(returnableOrder(customer: $this->customer));

    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertOk();
    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertConflict();
    returnsRequest('POST', "{$return->public_id}/approve", ['keep_items' => true], as: returnsStaffWith($this->store, ['returns.manage']))->assertForbidden();

    $this->store->run(static fn () => Event::assertDispatchedTimes(ReturnApproved::class, 1));
});

it('never announces a rejection that was refused, such as one of an approved return', function (): void {
    Event::fake([ReturnRejected::class]);
    $return = requestedReturn(returnableOrder(customer: $this->customer));
    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertOk();

    returnsRequest('POST', "{$return->public_id}/reject", ['reason' => 'No'], as: $this->owner)->assertConflict();

    $this->store->run(static fn () => Event::assertNotDispatched(ReturnRejected::class));
});

it('announces receipt once, and never for a return whose buyer keeps the items', function (): void {
    Event::fake([ReturnReceived::class]);
    $kept = requestedReturn(returnableOrder([[10000, 1]], $this->customer));
    returnsRequest('POST', "{$kept->public_id}/approve", ['keep_items' => true], as: $this->owner)->assertOk();
    $return = requestedReturn(returnableOrder([[10000, 1]], $this->customer));
    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertOk();

    returnsRequest('POST', "{$kept->public_id}/receive", [], as: $this->owner)->assertConflict();
    returnsRequest('POST', "{$return->public_id}/receive", [], as: $this->owner)->assertOk();
    returnsRequest('POST', "{$return->public_id}/receive", [], as: $this->owner)->assertConflict();

    $this->store->run(static function () use ($return): void {
        Event::assertDispatchedTimes(ReturnReceived::class, 1);
        Event::assertDispatched(ReturnReceived::class, static fn (ReturnReceived $event): bool => $event->return->is($return));
    });
});

it('announces an inspection only once its stock moves are committed, and never a refused one', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = receivedReturn($order);
    $seen = [];
    Event::listen(ReturnInspected::class, static function (ReturnInspected $event) use (&$seen): void {
        $seen[] = [$event->return->public_id, DB::transactionLevel(), StockMovement::query()->where('source_type', 'return')->where('source_id', $event->return->public_id)->count()];
    });

    returnsRequest('POST', "{$return->public_id}/inspect", ['lines' => [['line' => $order->lines[0]->public_id, 'restocked' => 0, 'written_off' => 0]]], as: $this->owner)->assertUnprocessable();
    returnsRequest('POST', "{$return->public_id}/inspect", ['lines' => [['line' => $order->lines[0]->public_id, 'restocked' => 1, 'written_off' => 0]]], as: $this->owner)->assertOk();

    // Outside any transaction, with the restock already saved.
    expect($seen)->toBe([[$return->public_id, 0, 1]]);
});

it('announces a cancellation by the buyer and by staff, and never a refused one', function (): void {
    Event::fake([ReturnCancelled::class]);
    $byBuyer = requestedReturn(returnableOrder([[10000, 1]], $this->customer));
    $byStaff = requestedReturn(returnableOrder([[10000, 1]], $this->customer));
    returnsRequest('POST', "{$byStaff->public_id}/approve", [], as: $this->owner)->assertOk();

    // A buyer can't call off an approved return.
    customerReturnRequest('POST', "returns/{$byStaff->public_id}/cancel", $this->customer)->assertConflict();
    $this->store->run(static fn () => Event::assertNotDispatched(ReturnCancelled::class));

    customerReturnRequest('POST', "returns/{$byBuyer->public_id}/cancel", $this->customer)->assertOk();
    returnsRequest('POST', "{$byStaff->public_id}/cancel", [], as: $this->owner)->assertOk();

    $this->store->run(static function () use ($byBuyer, $byStaff): void {
        Event::assertDispatchedTimes(ReturnCancelled::class, 2);

        foreach ([$byBuyer, $byStaff] as $return) {
            Event::assertDispatched(ReturnCancelled::class, static fn (ReturnCancelled $event): bool => $event->return->is($return));
        }

        expect(OrderReturn::query()->where('status', 'cancelled')->count())->toBe(2);
    });
});
