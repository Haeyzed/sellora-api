<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Returns\Actions\ApproveReturn;
use App\Tenant\Returns\Actions\InspectReturn;
use App\Tenant\Returns\Events\ReturnApproved;
use App\Tenant\Returns\Events\ReturnCancelled;
use App\Tenant\Returns\Events\ReturnInspected;
use App\Tenant\Returns\Events\ReturnReceived;
use App\Tenant\Returns\Events\ReturnRejected;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnStatusChange;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.6: staff taking a return through its steps (section 13). They
 * approve it (letting the buyer keep the items only if they can refund too),
 * reject it with a reason (while requested, or once inspected when nothing is
 * owed), mark its items received, and inspect them: each unit goes back on
 * the shelf or is written off, through Inventory, at an active location.
 * Staff can cancel before the items arrive. Each change works from the return
 * as saved under its lock, so two at the same moment never both happen.
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

it('approves a requested return, recording who did it, and announces it', function (): void {
    Event::fake([ReturnApproved::class]);
    $return = requestedReturn(returnableOrder(customer: $this->customer));

    $response = returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertOk();

    expect($response->json('data'))->toMatchArray(['status' => 'approved', 'keep_items' => false])
        ->and($response->json('data.approved_at'))->not->toBeNull();
    $this->store->run(function () use ($return): void {
        $change = ReturnStatusChange::query()->where('return_id', $return->id)->orderByDesc('id')->first();

        expect([$change?->from_status?->value, $change?->to_status->value, $change?->changed_by_id])->toBe(['requested', 'approved', $this->owner->public_id]);
        Event::assertDispatched(ReturnApproved::class, static fn (ReturnApproved $event): bool => $event->return->is($return));
    });

    // Once approved, it can't be approved again.
    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertConflict()->assertJsonPath('code', 'return_transition_not_allowed');
});

it('lets the buyer keep the items only when the staff member can refund too', function (): void {
    $return = requestedReturn(returnableOrder(customer: $this->customer));
    $manager = returnsStaffWith($this->store, ['returns.manage']);
    $refunder = returnsStaffWith($this->store, ['returns.manage', 'payments.refund']);

    returnsRequest('POST', "{$return->public_id}/approve", ['keep_items' => true], as: $manager)->assertForbidden();
    $this->store->run(static fn () => expect(OrderReturn::query()->sole()->status->value)->toBe('requested'));

    returnsRequest('POST', "{$return->public_id}/approve", ['keep_items' => true], as: $refunder)->assertOk()->assertJsonPath('data.keep_items', true);

    // Nothing comes back, so there is nothing to receive.
    returnsRequest('POST', "{$return->public_id}/receive", [], as: $this->owner)->assertConflict();
});

it('rejects a requested return with a reason the buyer sees, freeing its units to be asked for again', function (): void {
    Event::fake([ReturnRejected::class]);
    $order = returnableOrder(customer: $this->customer);
    $return = requestedReturn($order);

    returnsRequest('POST', "{$return->public_id}/reject", ['reason' => '  '], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('reason');
    returnsRequest('POST', "{$return->public_id}/reject", ['reason' => 'Bought in the sale'], as: $this->owner)->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Bought in the sale');

    customerReturnRequest('GET', "returns/{$return->public_id}", $this->customer)->assertOk()->assertJsonPath('data.rejection_reason', 'Bought in the sale');
    $this->store->run(static fn () => Event::assertDispatched(ReturnRejected::class, static fn (ReturnRejected $event): bool => $event->return->is($return)));

    // Its units count again.
    expect(requestedReturn($order)->sequence)->toBe(2);
});

it('rejects an inspected return by sending its units back through Inventory, so on hand is where it was before inspection', function (): void {
    $order = returnableOrder([[10000, 3], [5000, 2]], $this->customer);
    [$shirts, $socks] = [$order->lines[0], $order->lines[1]];
    $return = receivedReturn($order);
    $before = [onHandAtDefaultLocation($shirts), onHandAtDefaultLocation($socks)];
    returnsRequest('POST', "{$return->public_id}/inspect", ['lines' => [
        ['line' => $shirts->public_id, 'restocked' => 2, 'written_off' => 1],
        ['line' => $socks->public_id, 'restocked' => 0, 'written_off' => 2],
    ]], as: $this->owner)->assertOk();

    returnsRequest('POST', "{$return->public_id}/reject", ['reason' => 'Worn and washed'], as: $this->owner)->assertOk()->assertJsonPath('data.status', 'rejected');

    expect([onHandAtDefaultLocation($shirts), onHandAtDefaultLocation($socks)])->toBe($before);
    $this->store->run(function () use ($return, $shirts, $socks): void {
        $movements = static fn (int $variant): array => StockMovement::query()->where('source_type', 'return')->where('source_id', $return->public_id)->where('product_variant_id', $variant)
            ->orderBy('id')->get()->map(static fn (StockMovement $movement): array => [$movement->type->value, $movement->on_hand_delta])->all();

        // Inspection's movements stay as they were; the rejection adds its own.
        expect($movements($shirts->product_variant_id))->toBe([['restocked', 2], ['returned_damaged', 1], ['written_off', -1], ['write_off_reversed', 1], ['sent_back', -3]])
            ->and($movements($socks->product_variant_id))->toBe([['returned_damaged', 2], ['written_off', -2], ['write_off_reversed', 2], ['sent_back', -2]])
            ->and(StockMovement::query()->where('type', 'sent_back')->pluck('causer_id')->unique()->all())->toBe([$this->owner->public_id]);
    });

    expect(Artisan::call('inventory:reconcile'))->toBe(0);
});

it('refuses to reject an inspected return whose restocked units were sold since, changing nothing', function (): void {
    $order = returnableOrder([[10000, 2]], $this->customer);
    $return = receivedReturn($order);
    returnsRequest('POST', "{$return->public_id}/inspect", ['lines' => [['line' => $order->lines[0]->public_id, 'restocked' => 2, 'written_off' => 0]]], as: $this->owner)->assertOk();
    // Everything on the shelf is now held for other orders.
    $this->store->run(static fn () => StockItem::query()->where('product_variant_id', $order->lines[0]->product_variant_id)->update(['reserved' => DB::raw('on_hand')]));
    $onHand = onHandAtDefaultLocation($order->lines[0]);

    returnsRequest('POST', "{$return->public_id}/reject", ['reason' => 'Worn and washed'], as: $this->owner)->assertConflict()->assertJsonPath('code', 'returned_units_no_longer_on_hand');

    expect(onHandAtDefaultLocation($order->lines[0]))->toBe($onHand);
    $this->store->run(static fn () => expect(OrderReturn::query()->sole()->status->value)->toBe('inspected')
        ->and(StockMovement::query()->where('type', 'sent_back')->exists())->toBeFalse());
});

it('refuses to reject an approved or received return, which is on its way back or already here', function (): void {
    $approved = requestedReturn(returnableOrder([[10000, 1]], $this->customer));
    returnsRequest('POST', "{$approved->public_id}/approve", [], as: $this->owner)->assertOk();
    $received = receivedReturn(returnableOrder([[10000, 1]], $this->customer));

    returnsRequest('POST', "{$approved->public_id}/reject", ['reason' => 'No'], as: $this->owner)->assertConflict();
    returnsRequest('POST', "{$received->public_id}/reject", ['reason' => 'No'], as: $this->owner)->assertConflict();
});

it('marks an approved return received, moving no stock yet', function (): void {
    Event::fake([ReturnReceived::class]);
    $order = returnableOrder(customer: $this->customer);
    $return = requestedReturn($order);
    $onHand = onHandAtDefaultLocation($order->lines[0]);

    returnsRequest('POST', "{$return->public_id}/receive", [], as: $this->owner)->assertConflict();
    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertOk();
    returnsRequest('POST', "{$return->public_id}/receive", [], as: $this->owner)->assertOk()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.received_at', fn (?string $at): bool => $at !== null);

    expect(onHandAtDefaultLocation($order->lines[0]))->toBe($onHand);
    $this->store->run(static fn () => Event::assertDispatched(ReturnReceived::class, static fn (ReturnReceived $event): bool => $event->return->is($return)));
});

it('inspects a received return through Inventory: units back on the shelf or written off, with the return as the source', function (): void {
    Event::fake([ReturnInspected::class]);
    $order = returnableOrder([[10000, 3], [5000, 1]], $this->customer);
    [$shirts, $socks] = [$order->lines[0], $order->lines[1]];
    $return = receivedReturn($order);
    [$shirtsOnHand, $socksOnHand] = [onHandAtDefaultLocation($shirts), onHandAtDefaultLocation($socks)];

    $response = returnsRequest('POST', "{$return->public_id}/inspect", ['lines' => [
        ['line' => $shirts->public_id, 'restocked' => 2, 'written_off' => 1],
        ['line' => $socks->public_id, 'restocked' => 0, 'written_off' => 1],
    ]], as: $this->owner)->assertOk();

    expect($response->json('data.status'))->toBe('inspected')
        ->and(array_map(static fn (array $line): array => [$line['restocked_quantity'], $line['written_off_quantity']], array_column($response->json('data.lines'), null, 'line')))->toBe([
            $shirts->public_id => [2, 1],
            $socks->public_id => [0, 1],
        ])
        ->and([onHandAtDefaultLocation($shirts), onHandAtDefaultLocation($socks)])->toBe([$shirtsOnHand + 2, $socksOnHand]);

    $this->store->run(function () use ($return, $shirts): void {
        $location = StockLocation::query()->where('is_default', true)->sole();
        $movements = StockMovement::query()->where('source_type', 'return')->where('source_id', $return->public_id)->orderBy('id')->get();

        expect(OrderReturn::query()->sole()->stock_location_id)->toBe($location->id)
            ->and($movements->where('product_variant_id', $shirts->product_variant_id)->map(static fn (StockMovement $movement): array => [$movement->type->value, $movement->on_hand_delta])->values()->all())->toBe([
                ['restocked', 2],
                ['returned_damaged', 1],
                ['written_off', -1],
            ])
            ->and($movements->pluck('causer_id')->unique()->all())->toBe([$this->owner->public_id]);
        Event::assertDispatched(ReturnInspected::class, static fn (ReturnInspected $event): bool => $event->return->is($return));
    });

    expect(Artisan::call('inventory:reconcile'))->toBe(0);

    // Inspected once: never again.
    returnsRequest('POST', "{$return->public_id}/inspect", ['lines' => [['line' => $shirts->public_id, 'restocked' => 3, 'written_off' => 0], ['line' => $socks->public_id, 'restocked' => 1, 'written_off' => 0]]], as: $this->owner)->assertConflict();
});

it('inspects at the location chosen, only an active one', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = receivedReturn($order);
    [$backRoom, $closed] = $this->store->run(static function (): array {
        $backRoom = StockLocation::query()->create(['name' => 'Back room']);
        $closed = StockLocation::query()->create(['name' => 'Closed']);
        $closed->forceFill(['is_active' => false])->save();

        return [$backRoom, $closed];
    });
    $lines = [['line' => $order->lines[0]->public_id, 'restocked' => 1, 'written_off' => 0]];

    returnsRequest('POST', "{$return->public_id}/inspect", ['location' => $closed->public_id, 'lines' => $lines], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('location');
    returnsRequest('POST', "{$return->public_id}/inspect", ['location' => $backRoom->public_id, 'lines' => $lines], as: $this->owner)->assertOk()->assertJsonPath('data.stock_location', $backRoom->public_id);

    $this->store->run(static fn () => expect(StockItem::query()->where('stock_location_id', $backRoom->id)->where('product_variant_id', $order->lines[0]->product_variant_id)->sole()->on_hand)->toBe(1));
});

it('refuses an inspection that does not say what became of every unit', function (): void {
    $order = returnableOrder([[10000, 2], [5000, 1]], $this->customer);
    [$shirts, $socks] = [$order->lines[0]->public_id, $order->lines[1]->public_id];
    $return = receivedReturn($order, [$shirts => 2]);
    $inspect = fn (array $lines) => returnsRequest('POST', "{$return->public_id}/inspect", ['lines' => $lines], as: $this->owner);

    // Not adding up to the line's quantity.
    $inspect([['line' => $shirts, 'restocked' => 1, 'written_off' => 0]])->assertUnprocessable()->assertJsonPath('code', 'return_inspection_mismatch')->assertJsonValidationErrors(["lines.{$shirts}"]);
    // A line that isn't the return's, alongside one that is.
    $inspect([['line' => $shirts, 'restocked' => 2, 'written_off' => 0], ['line' => $socks, 'restocked' => 1, 'written_off' => 0]])->assertUnprocessable()->assertJsonValidationErrors(["lines.{$socks}"]);
    // The return's line left out.
    $inspect([['line' => $socks, 'restocked' => 1, 'written_off' => 0]])->assertUnprocessable()->assertJsonValidationErrors(["lines.{$shirts}", "lines.{$socks}"]);
    $inspect([['line' => $shirts, 'restocked' => -1, 'written_off' => 3]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.restocked');

    $this->store->run(static fn () => expect(OrderReturn::query()->sole()->status->value)->toBe('received')
        ->and(StockMovement::query()->where('source_type', 'return')->exists())->toBeFalse());
});

it('lets staff cancel a return before its items arrive, never after', function (): void {
    Event::fake([ReturnCancelled::class]);
    $approved = requestedReturn(returnableOrder([[10000, 1]], $this->customer));
    returnsRequest('POST', "{$approved->public_id}/approve", [], as: $this->owner)->assertOk();
    $received = receivedReturn(returnableOrder([[10000, 1]], $this->customer));

    returnsRequest('POST', "{$approved->public_id}/cancel", [], as: $this->owner)->assertOk()->assertJsonPath('data.status', 'cancelled');
    returnsRequest('POST', "{$received->public_id}/cancel", [], as: $this->owner)->assertConflict();

    $this->store->run(static fn () => Event::assertDispatched(ReturnCancelled::class, static fn (ReturnCancelled $event): bool => $event->return->is($approved)));
});

it('lets only staff who can manage returns move them along', function (): void {
    $return = requestedReturn(returnableOrder(customer: $this->customer));
    $viewer = returnsStaffWith($this->store, ['returns.view']);

    foreach (['approve' => [], 'reject' => ['reason' => 'No'], 'receive' => [], 'inspect' => ['lines' => [['line' => 'x', 'restocked' => 0, 'written_off' => 0]]], 'cancel' => []] as $step => $data) {
        returnsRequest('POST', "{$return->public_id}/{$step}", $data, as: $viewer)->assertForbidden();
        returnsRequest('POST', "{$return->public_id}/{$step}", $data)->assertUnauthorized();
    }

    $this->store->run(static fn () => expect(OrderReturn::query()->sole()->status->value)->toBe('requested'));

    ['owner' => $otherOwner] = returnsStore('second-store');
    returnsRequest('POST', "{$return->public_id}/approve", [], as: $otherOwner, subdomain: 'second-store')->assertNotFound();
});

it('streams a return\'s photos by position only to staff who can view returns, never cached', function (): void {
    $order = returnableOrder(customer: $this->customer);
    $id = customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, [
        'lines' => returnLinesPayload([$order->lines[0]->public_id => 1]),
        'reason' => 'damaged',
        'photos' => [returnPhoto()],
    ])->assertCreated()->json('data.id');
    $kept = $this->store->run(static fn (): string => (string) Storage::disk(config()->string('media.return_photos.disk'))->get(Media::query()->sole()->getPathRelativeToRoot()));
    $viewer = returnsStaffWith($this->store, ['returns.view']);

    $response = returnsRequest('GET', "{$id}/photos/1", as: $viewer)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->streamedContent())->toBe($kept)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    returnsRequest('GET', "{$id}/photos/2", as: $viewer)->assertNotFound();
    returnsRequest('GET', "{$id}/photos/0", as: $viewer)->assertNotFound();
    returnsRequest('GET', "{$id}/photos/1", as: returnsStaffWith($this->store, ['returns.manage']))->assertForbidden();
    returnsRequest('GET', "{$id}/photos/1")->assertUnauthorized();
});

it('queues two approvals of the same return on its lock, so only one happens', function (): void {
    $return = requestedReturn(returnableOrder(customer: $this->customer));

    $this->store->run(function () use ($return): void {
        $approve = fn () => app(ApproveReturn::class)->handle($return, AccountReference::to($this->owner));

        // Another request holds the return and has approved it, not yet committed.
        $otherRequest = SecondConnection::open()->holdRowLock(OrderReturn::query()->whereKey($return->id)->sole());
        $otherRequest->run(fn () => DB::connection(SecondConnection::NAME)->table('returns')->where('id', $return->id)->update(['status' => 'approved', 'approved_at' => now()]));

        expect($otherRequest->blockedStatement($approve))->toContain('"returns"')->toContain('for update');

        $otherRequest->commit();

        // Worked from the approval the other request committed, not the request it could have read before.
        expect($approve)->toThrow(ReturnTransitionNotAllowedException::class)
            ->and(ReturnStatusChange::query()->where('return_id', $return->id)->where('to_status', 'approved')->count())->toBe(0);
    });
});

it('queues two rejections or cancellations of the same return on its lock, so only one happens', function (string $step, array $change): void {
    $return = requestedReturn(returnableOrder(customer: $this->customer));

    $this->store->run(function () use ($return, $step, $change): void {
        $act = fn () => $step === 'reject'
            ? app(App\Tenant\Returns\Actions\RejectReturn::class)->handle($return, 'Too late', AccountReference::to($this->owner))
            : app(App\Tenant\Returns\Actions\CancelReturn::class)->handle($return, App\Tenant\Returns\Data\ReturnRequester::staff($this->owner));

        // Another request holds the return and has changed it the same way, not yet committed.
        $otherRequest = SecondConnection::open()->holdRowLock(OrderReturn::query()->whereKey($return->id)->sole());
        $otherRequest->run(fn () => DB::connection(SecondConnection::NAME)->table('returns')->where('id', $return->id)->update($change));

        expect($otherRequest->blockedStatement($act))->toContain('"returns"')->toContain('for update');

        $otherRequest->commit();

        expect($act)->toThrow(ReturnTransitionNotAllowedException::class)
            ->and(ReturnStatusChange::query()->where('return_id', $return->id)->count())->toBe(1);
    });
})->with([
    'two rejections' => ['reject', ['status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => 'No']],
    'two cancellations' => ['cancel', ['status' => 'cancelled', 'cancelled_at' => now()]],
]);

it('queues two inspections of the same return on its lock, so stock moves only once', function (): void {
    $order = returnableOrder([[10000, 2]], $this->customer);
    $return = receivedReturn($order);
    $onHand = onHandAtDefaultLocation($order->lines[0]);

    $this->store->run(function () use ($return, $order): void {
        $location = StockLocation::query()->where('is_default', true)->sole();
        $inspect = fn () => app(InspectReturn::class)->handle($return, $location, [$order->lines[0]->public_id => ['restocked' => 2, 'written_off' => 0]], AccountReference::to($this->owner));

        // Another request holds the return and has inspected it, not yet committed.
        $otherRequest = SecondConnection::open()->holdRowLock(OrderReturn::query()->whereKey($return->id)->sole());
        $otherRequest->run(fn () => DB::connection(SecondConnection::NAME)->table('returns')->where('id', $return->id)->update(['status' => 'inspected', 'inspected_at' => now(), 'stock_location_id' => $location->id]));

        expect($otherRequest->blockedStatement($inspect))->toContain('"returns"')->toContain('for update');

        $otherRequest->commit();

        expect($inspect)->toThrow(ReturnTransitionNotAllowedException::class)
            ->and(StockMovement::query()->where('source_type', 'return')->exists())->toBeFalse();
    });

    expect(onHandAtDefaultLocation($order->lines[0]))->toBe($onHand);
});
