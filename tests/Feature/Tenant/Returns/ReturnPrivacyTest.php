<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Privacy\ExportedFile;
use App\Shared\Privacy\PersonalDataErasure;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Privacy\StoreExportRegistry;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Privacy\GuestDataSubject;
use App\Tenant\Orders\Services\OrderAnonymisation;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\ReturnPhotoRetention;
use App\Tenant\Returns\ReturnStoreFiles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.8: returns and personal data (section 13). A return's note and
 * photos are the buyer's personal data: cleared when their order is
 * anonymised, kept in their data export, and the photos deleted 180 days
 * after the return ends. Nothing new about the buyer of an anonymised order
 * is kept, and the photos are in the store's export until purged.
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

it('clears a return\'s note and photos when its order is anonymised, keeping the return', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $id = returnWithPhoto($order, $this->customer);
    expect(returnPhotoFiles())->toHaveCount(1);

    $this->store->run(static fn () => app(PersonalDataErasure::class)->run(static fn () => app(OrderAnonymisation::class)->anonymise(Order::query()->sole())));

    $this->store->run(static function (): void {
        $return = OrderReturn::query()->sole();

        expect([$return->note, $return->anonymised_at !== null, $return->status->value, $return->lines()->sum('quantity')])->toBe([null, true, 'requested', 1])
            ->and(Media::query()->count())->toBe(0);
    });
    expect(returnPhotoFiles())->toBe([]);
    returnsRequest('GET', $id, as: $this->owner)->assertOk()->assertJsonPath('data.note', null)->assertJsonPath('data.photos', 0);
});

it('keeps the note and photos when the erasure run rolls back', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    returnWithPhoto($order, $this->customer);

    $this->store->run(static function (): void {
        expect(static fn () => app(PersonalDataErasure::class)->run(static function (): void {
            app(OrderAnonymisation::class)->anonymise(Order::query()->sole());

            throw new RuntimeException('Something later in the erasure failed.');
        }))->toThrow(RuntimeException::class);

        expect(OrderReturn::query()->sole()->note)->not->toBeNull()
            ->and(Media::query()->count())->toBe(1);
    });

    expect(returnPhotoFiles())->toHaveCount(1);
});

it('keeps nothing new about the buyer of an anonymised order', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $this->store->run(static fn () => app(PersonalDataErasure::class)->run(static fn () => app(OrderAnonymisation::class)->anonymise(Order::query()->sole())));

    startReturnAsStaff($order, ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'damaged', 'note' => 'From a phone call'], $this->owner)
        ->assertConflict()->assertJsonPath('code', 'order_not_returnable');
});

it('puts a customer\'s returns, and a guest\'s, in their data export', function (): void {
    $customerOrder = returnableOrder([[10000, 1]], $this->customer);
    $guestOrder = returnableOrder([[5000, 1]]);
    returnWithPhoto($customerOrder, $this->customer);
    requestedReturn($guestOrder);

    $customerExport = $this->store->run(fn (): array => app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($this->customer)));
    $guestExport = $this->store->run(static fn (): array => app(PersonalDataRegistry::class)->export(GuestDataSubject::of((string) $guestOrder->contact_email)));

    expect($customerExport['returns'])->toHaveCount(1)
        ->and($customerExport['returns'][0])->toMatchArray([
            'number' => '#'.$customerOrder->number.'-R1',
            'order' => $customerOrder->number,
            'reason' => 'damaged',
            'note' => 'Arrived cracked, call me on 0803 000 0000',
            'photos' => 1,
            'lines' => [['product' => $customerOrder->lines[0]->product_name, 'variant' => $customerOrder->lines[0]->variant_name, 'quantity' => 1]],
        ])
        ->and(array_column($guestExport['returns'], 'order'))->toBe([$guestOrder->number]);
});

it('deletes return photos 180 days after their return ended, keeping those of open or more recent returns', function (): void {
    $ended = returnWithPhoto(returnableOrder([[10000, 1]], $this->customer), $this->customer);
    $recent = returnWithPhoto(returnableOrder([[10000, 1]], $this->customer), $this->customer);
    $open = returnWithPhoto(returnableOrder([[10000, 1]], $this->customer), $this->customer);
    $this->store->run(static function () use ($ended, $recent, $open): void {
        $days = config()->integer('retention.periods.return_photos');
        OrderReturn::query()->where('public_id', $ended)->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()->subDays($days + 1)]);
        OrderReturn::query()->where('public_id', $recent)->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()->subDays($days - 1)]);
        // An open return asked for long ago keeps its photos until it ends.
        OrderReturn::query()->where('public_id', $open)->update(['requested_at' => CarbonImmutable::now()->subDays($days * 2)]);
    });

    $purged = $this->store->run(static fn (): int => app(ReturnPhotoRetention::class)->purgeOlderThan(now()->subDays(config()->integer('retention.periods.return_photos'))->toImmutable()));

    expect([$purged, config()->integer('retention.periods.return_photos')])->toBe([1, 180])
        ->and(returnPhotoFiles())->toHaveCount(2);
    $this->store->run(static fn () => expect(OrderReturn::query()->whereHas('media')->orderBy('id')->pluck('public_id')->all())->toBe([$recent, $open])
        ->and(OrderReturn::query()->count())->toBe(3));
});

it('puts return photos into the store export as kept', function (): void {
    returnWithPhoto(returnableOrder([[10000, 1]], $this->customer), $this->customer);

    expect(app(StoreExportRegistry::class)->fileSources())->toContain(ReturnStoreFiles::class);

    $this->store->run(static function (): void {
        $files = [...app(ReturnStoreFiles::class)->files()];

        expect(array_map(static fn (ExportedFile $file): string => $file->path, $files))->toHaveCount(1)
            ->and($files[0]->path)->toStartWith('return/')
            ->and(stream_get_contents(($files[0]->openStream)()))->toBe((string) Storage::disk(config()->string('media.return_photos.disk'))->get(Media::query()->sole()->getPathRelativeToRoot()));
    });
});
