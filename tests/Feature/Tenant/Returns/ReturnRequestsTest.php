<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Returns\Actions\ChangeReturnSettings;
use App\Tenant\Returns\Events\ReturnCancelled;
use App\Tenant\Returns\Events\ReturnRequested;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnStatusChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.5: asking for a return (section 13). A customer for their own
 * order, a guest with its access token in the X-Order-Token header, or staff
 * for the buyer. Buyers return delivered units within the store's window
 * (14 days from delivery by default), while the store takes their requests,
 * with a photo when the store requires one for damage or the wrong item.
 * Staff can always start one, saying why when they go past the window or
 * return units shipped but never marked delivered. Units already in an open
 * return or refunded by line can't be returned again, read under the order's
 * returns lock. Photos are re-encoded, private and counted against storage.
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

it('lets a customer ask to return delivered units of their order, recorded with who asked', function (): void {
    Event::fake([ReturnRequested::class]);
    $order = returnableOrder([[10000, 2], [5000, 1]], $this->customer);
    [$shirts, $socks] = [$order->lines[0]->public_id, $order->lines[1]->public_id];

    $response = customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, [
        'lines' => returnLinesPayload([$shirts => 2, $socks => 1]),
        'reason' => 'does_not_fit',
        'note' => 'Too small',
    ])->assertCreated();

    expect($response->json('data'))->toMatchArray([
        'number' => '#'.$order->number.'-R1',
        'status' => 'requested',
        'reason' => ['code' => 'does_not_fit', 'label' => "Doesn't fit"],
        'note' => 'Too small',
        'keep_items' => false,
        'photos' => 0,
    ])
        ->and(array_column($response->json('data.lines'), 'quantity', 'line'))->toBe([$shirts => 2, $socks => 1])
        // What staff record for themselves stays with staff.
        ->and($response->json('data'))->not->toHaveKeys(['requested_by', 'outside_window_reason', 'undelivered_reason', 'is_test']);

    $this->store->run(function () use ($response): void {
        $return = OrderReturn::query()->where('public_id', $response->json('data.id'))->sole();
        $change = ReturnStatusChange::query()->sole();

        expect([$return->requested_by_type, $return->requested_by_id, $return->is_test])->toBe(['customer', $this->customer->public_id, false])
            ->and([$change->from_status, $change->to_status->value, $change->changed_by_id])->toBe([null, 'requested', $this->customer->public_id]);
        Event::assertDispatched(ReturnRequested::class, static fn (ReturnRequested $event): bool => $event->return->is($return));
    });
});

it('lets a guest ask with their order\'s token in the header, and nobody without it', function (): void {
    $order = returnableOrder();
    $token = guestOrderToken($order);
    $data = ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'changed_mind'];

    guestReturnRequest('POST', "{$order->public_id}/returns", null, $data)->assertNotFound();
    // A token in the URL is refused outright: tokens never travel there.
    guestReturnRequest('POST', "{$order->public_id}/returns?token={$token}", null, $data)->assertStatus(400)->assertJsonPath('code', 'secret_in_url');
    guestReturnRequest('POST', "{$order->public_id}/returns", 'not-the-token', $data)->assertNotFound();
    $created = guestReturnRequest('POST', "{$order->public_id}/returns", $token, $data)->assertCreated();

    guestReturnRequest('GET', "{$order->public_id}/returns", $token)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $created->json('data.id'));
    expect($this->store->run(static fn (): array => OrderReturn::query()->sole()->only(['requested_by_type', 'requested_by_id'])))->toBe(['requested_by_type' => null, 'requested_by_id' => null]);
});

it('never lets a customer reach another buyer\'s order or return', function (): void {
    $other = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $order = returnableOrder(customer: $other);
    $data = ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'changed_mind'];

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, $data)->assertNotFound();
    $return = customerReturnRequest('POST', "orders/{$order->public_id}/returns", $other, $data)->assertCreated()->json('data.id');

    customerReturnRequest('GET', "returns/{$return}", $this->customer)->assertNotFound();
    customerReturnRequest('POST', "returns/{$return}/cancel", $this->customer)->assertNotFound();
    customerReturnRequest('GET', 'returns', $this->customer)->assertOk()->assertJsonCount(0, 'data');
    customerReturnRequest('GET', 'returns', $other)->assertOk()->assertJsonCount(1, 'data');
});

it('refuses more units than are left of a line: never delivered, in another open return, or refunded', function (): void {
    useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
    $order = returnableOrder([[10000, 3]], $this->customer, paidByGateway: true);
    $line = $order->lines[0]->public_id;
    $ask = fn (int $quantity) => customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, ['lines' => returnLinesPayload([$line => $quantity]), 'reason' => 'changed_mind']);

    $ask(4)->assertUnprocessable()->assertJsonPath('code', 'return_quantity_too_high')->assertJsonValidationErrors(["lines.{$line}" => 'Only 3 of this line can still be returned.']);
    $first = $ask(2)->assertCreated()->json('data.id');
    $ask(2)->assertUnprocessable()->assertJsonValidationErrors(["lines.{$line}" => 'Only 1 of this line can still be returned.']);

    // A refund of the last unit by line uses it up too.
    refundOrder($order, lines: [$line => 1]);
    $ask(1)->assertUnprocessable()->assertJsonPath('code', 'return_quantity_too_high');

    // Calling the first return off frees its units again.
    customerReturnRequest('POST', "returns/{$first}/cancel", $this->customer)->assertOk()->assertJsonPath('data.status', 'cancelled');
    $ask(2)->assertCreated();
});

it('lets buyers return only units already delivered, and staff shipped ones too, saying why', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, deliver: false);
    $line = $order->lines[0]->public_id;

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, ['lines' => returnLinesPayload([$line => 1]), 'reason' => 'damaged'])
        ->assertUnprocessable()->assertJsonValidationErrors(["lines.{$line}" => 'Only 0 of this line can still be returned.']);
    startReturnAsStaff($order, ['lines' => returnLinesPayload([$line => 1]), 'reason' => 'damaged'], $this->owner)
        ->assertUnprocessable()->assertJsonPath('code', 'return_staff_reason_required')->assertJsonValidationErrors('undelivered_reason');
    startReturnAsStaff($order, ['lines' => returnLinesPayload([$line => 1]), 'reason' => 'damaged', 'undelivered_reason' => 'Buyer says it arrived; the courier never confirmed'], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.undelivered_reason', 'Buyer says it arrived; the courier never confirmed')
        ->assertJsonPath('data.requested_by', ['type' => 'staff_member', 'id' => $this->owner->public_id]);
});

it('holds buyers to the window after delivery, while staff go past it with a reason', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer, deliveredAt: CarbonImmutable::now()->subDays(15));
    $data = ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'changed_mind'];

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, $data)->assertUnprocessable()->assertJsonPath('code', 'return_window_closed');
    startReturnAsStaff($order, $data, $this->owner)->assertUnprocessable()->assertJsonValidationErrors('outside_window_reason');

    // A longer window lets the buyer ask after all.
    $this->store->run(static fn () => app(ChangeReturnSettings::class)->handle(windowDays: 30));
    $created = customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, $data)->assertCreated()->json('data.id');
    customerReturnRequest('POST', "returns/{$created}/cancel", $this->customer)->assertOk();

    $this->store->run(static fn () => app(ChangeReturnSettings::class)->handle(windowDays: 14));
    startReturnAsStaff($order, [...$data, 'outside_window_reason' => 'Buyer was travelling'], $this->owner)
        ->assertCreated()->assertJsonPath('data.outside_window_reason', 'Buyer was travelling');
});

it('takes buyers\' requests only while the store does, and staff\'s always', function (array $settings): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $data = ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'changed_mind'];
    $this->store->run(static fn () => app(ChangeReturnSettings::class)->handle(...$settings));

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, $data)->assertForbidden()->assertJsonPath('code', 'returns_not_offered');
    $guestOrder = returnableOrder([[10000, 1]]);
    guestReturnRequest('POST', "{$guestOrder->public_id}/returns", guestOrderToken($guestOrder), [...$data, 'lines' => returnLinesPayload([$guestOrder->lines[0]->public_id => 1])])->assertForbidden()->assertJsonPath('code', 'returns_not_offered');
    // With staff-only returns, staff need no reason for how long ago it was delivered.
    startReturnAsStaff($order, $data, $this->owner)->assertCreated();
})->with([
    'buyer requests off' => [['customerRequests' => false]],
    'a window of 0 days' => [['windowDays' => 0]],
]);

it('refuses returns on an order that was never sold, or was cancelled', function (): void {
    $unpaid = $this->store->run(static fn (): Order => unpaidOrder());
    // Paid, then cancelled before it shipped: the money goes back by refund, never by a return.
    $cancelled = $this->store->run(fn (): Order => app(CancelOrder::class)->handle(markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod()))), CancellationReason::Staff, App\Shared\Auth\AccountReference::to($this->owner)));

    foreach ([$unpaid, $cancelled] as $order) {
        startReturnAsStaff($order, ['lines' => returnLinesPayload([$this->store->run(static fn (): string => $order->lines()->firstOrFail()->public_id) => 1]), 'reason' => 'damaged'], $this->owner)
            ->assertConflict()->assertJsonPath('code', 'order_not_returnable');
    }
});

it('keeps photos re-encoded on the private disk, and requires one for damage or the wrong item only when the store does', function (): void {
    $order = returnableOrder([[10000, 3]], $this->customer);
    $line = $order->lines[0]->public_id;
    $ask = fn (string $reason, array $photos = []) => customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, ['lines' => returnLinesPayload([$line => 1]), 'reason' => $reason, 'photos' => $photos]);

    $ask('damaged')->assertCreated()->assertJsonPath('data.photos', 0);
    $this->store->run(static fn () => app(ChangeReturnSettings::class)->handle(photosRequired: true));
    $ask('wrong_item')->assertUnprocessable()->assertJsonPath('code', 'return_photo_required');
    // Another reason needs none.
    $ask('changed_mind')->assertCreated();
    $photo = returnPhoto();
    $withPhoto = $ask('damaged', [$photo])->assertCreated()->assertJsonPath('data.photos', 1);

    $this->store->run(function () use ($withPhoto): void {
        $media = Media::query()->where('collection_name', 'photos')->sole();

        expect($media->disk)->toBe(config()->string('media.return_photos.disk'))
            ->and($media->model_id)->toBe(OrderReturn::query()->where('public_id', $withPhoto->json('data.id'))->value('id'))
            ->and($media->file_name)->toBe('photo-1.jpg');
    });
    expect(returnPhotoFiles())->toHaveCount(1);
});

it('refuses a photo that isn\'t an image it can read, keeping nothing', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, [
        'lines' => returnLinesPayload([$order->lines[0]->public_id => 1]),
        'reason' => 'damaged',
        'photos' => [Illuminate\Http\UploadedFile::fake()->create('damage.pdf', 10, 'application/pdf')],
    ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

    expect($this->store->run(static fn (): int => OrderReturn::query()->count()))->toBe(0)
        ->and(returnPhotoFiles())->toBe([]);
});

it('keeps no photo when the return can\'t be saved, such as the store being out of storage', function (): void {
    $this->featureSource->give($this->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['storage_in_megabytes' => 0]));
    app(App\Shared\Features\Features::class)->forget($this->store->getTenantKey());
    $order = returnableOrder([[10000, 1]], $this->customer);

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, [
        'lines' => returnLinesPayload([$order->lines[0]->public_id => 1]),
        'reason' => 'damaged',
        'photos' => [returnPhoto()],
    ])->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');

    expect($this->store->run(static fn (): array => [OrderReturn::query()->count(), Media::query()->count()]))->toBe([0, 0])
        ->and(returnPhotoFiles())->toBe([]);
});

it('lets the buyer call a return off only while it is requested', function (): void {
    Event::fake([ReturnCancelled::class]);
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'changed_mind'])->json('data.id');
    $this->store->run(static fn () => OrderReturn::query()->where('public_id', $return)->update(['status' => 'approved', 'approved_at' => now()]));

    customerReturnRequest('POST', "returns/{$return}/cancel", $this->customer)->assertConflict()->assertJsonPath('code', 'return_transition_not_allowed');
    Event::assertNotDispatched(ReturnCancelled::class);

    $this->store->run(static fn () => OrderReturn::query()->where('public_id', $return)->update(['status' => 'requested', 'approved_at' => null]));
    customerReturnRequest('POST', "returns/{$return}/cancel", $this->customer)->assertOk()->assertJsonPath('data.status', 'cancelled');
    Event::assertDispatchedTimes(ReturnCancelled::class, 1);
});

it('names the reasons in the language asked for, when the store publishes in it', function (): void {
    $this->store->run(static fn () => App\Tenant\Settings\Models\StoreSettings::query()->sole()->forceFill(['enabled_locales' => ['en', 'de']])->save());
    $this->withHeader('Accept-Language', 'de');
    $response = $this->getJson(storeUrl('first-store', '/api/v1/returns/reasons'))->assertOk();
    tenancy()->end();

    expect(array_column($response->json('data'), 'label', 'code'))->toMatchArray(['damaged' => 'Beschädigt angekommen', 'other' => 'Anderer Grund'])
        ->and(array_column($response->json('data'), 'stores_fault', 'code'))->toMatchArray(['damaged' => true, 'wrong_item' => true, 'changed_mind' => false]);
});

it('limits how often a buyer can ask, per store', function (): void {
    config(['api.rate_limits.return_requests' => 2]);
    $order = returnableOrder([[10000, 5]], $this->customer);
    $ask = fn () => customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'changed_mind']);

    $ask()->assertCreated();
    $ask()->assertCreated();
    $ask()->assertTooManyRequests();
});

it('queues two requests for the same order on its returns lock, so they never claim the same units', function (): void {
    $order = returnableOrder([[10000, 2]], $this->customer);

    $this->store->run(function () use ($order): void {
        $ask = fn () => app(App\Tenant\Returns\Actions\RequestReturn::class)->handle(App\Tenant\Returns\Data\ReturnRequester::customer($this->customer), $order, [$order->lines[0]->public_id => 2], App\Tenant\Returns\Enums\ReturnReason::ChangedMind);

        // Another request holds the order's returns and has claimed both units, not yet committed.
        $otherRequest = SecondConnection::open()->holdAdvisoryLock('returns:order:'.$order->id);
        $otherRequest->run(fn () => DB::connection(SecondConnection::NAME)->table('returns')->insertGetId([
            'public_id' => (string) Illuminate\Support\Str::ulid(), 'order_id' => $order->id, 'sequence' => 1, 'status' => 'requested', 'resolution' => 'refund',
            'reason' => 'changed_mind', 'is_test' => false, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        $otherRequest->run(fn () => DB::connection(SecondConnection::NAME)->table('return_lines')->insert([
            'return_id' => DB::connection(SecondConnection::NAME)->table('returns')->where('order_id', $order->id)->value('id'), 'order_line_id' => $order->lines[0]->id, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]));

        expect($otherRequest->blockedStatement($ask))->toContain('pg_advisory_xact_lock');

        $otherRequest->commit();

        // Worked from what the other request claimed, not what it could have read before.
        expect($ask)->toThrow(App\Tenant\Returns\Exceptions\ReturnQuantityTooHighException::class)
            ->and(OrderReturn::query()->count())->toBe(1);
    });
});
