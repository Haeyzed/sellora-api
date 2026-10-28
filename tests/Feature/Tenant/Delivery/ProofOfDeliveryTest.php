<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\StorageLimit;
use App\Shared\Privacy\ExportedFile;
use App\Tenant\Delivery\Actions\MoveDelivery;
use App\Tenant\Delivery\DeliveryStoreFiles;
use App\Tenant\Delivery\Exceptions\ProofOfDeliveryRequiredException;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\ProofOfDeliveryRetention;
use App\Tenant\Delivery\Services\ProofOfDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 10, Step 17: proof of delivery. Delivering always needs the name of
 * who took the goods and a photo of them handed over, the recipient's
 * signature, or both. The images are personal data: they are re-encoded
 * without their metadata, kept on the store's private disk with the
 * delivery, count towards the plan's storage (checked under its lock), and
 * are only streamed to staff with delivery.view. They are saved with the
 * delivery or not at all, and purged 180 days after it.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['store' => $this->store, 'owner' => $this->owner, 'driver' => $this->driver, 'token' => $this->token] = deliveryStore();
    $this->delivery = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$this->delivery}/pick-up", token: $this->token)->assertOk();
});

afterEach(function (): void {
    deleteAllStores();
});

it('needs a photo or a signature, and keeps either or both with the delivery', function (): void {
    handOver([])->assertUnprocessable()->assertJsonValidationErrors(['photo', 'signature']);
    expect($this->store->run(fn (): string => Delivery::query()->sole()->status->value))->toBe('picked_up');

    handOver(['signature' => proofPhoto('signature.png', 300, 100)])->assertOk()->assertJsonPath('data.status', 'delivered');

    $this->store->run(static function (): void {
        $media = Media::query()->sole();

        expect([$media->model_type, $media->collection_name, $media->disk, $media->file_name, $media->mime_type])
            ->toBe(['delivery', 'recipient_signature', 'local', 'signature.png', 'image/png'])
            ->and(Storage::disk('local')->exists($media->getPathRelativeToRoot()))->toBeTrue()
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });

    deliveryRequest('GET', "deliveries/{$this->delivery}", as: $this->owner)->assertOk()->assertJsonPath('data.proof', ['photo' => false, 'signature' => true]);
});

it('refuses delivering without proof in the Action too, whatever calls it', function (): void {
    $this->store->run(function (): void {
        expect(fn () => app(MoveDelivery::class)->deliver(Delivery::query()->sole(), AccountReference::to($this->driver), 'Ada Obi', new ProofOfDelivery))
            ->toThrow(ProofOfDeliveryRequiredException::class)
            ->and(Delivery::query()->sole()->status->value)->toBe('picked_up');
    });
});

it('accepts only JPEG, PNG or WebP images within the size and dimension limits', function (): void {
    $svg = UploadedFile::fake()->createWithContent('signature.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    // A PNG whose header is fine but whose pixels can't be decoded.
    $real = proofPhoto('real.png', 10, 10);
    $png = (string) file_get_contents($real->getRealPath());
    $broken = UploadedFile::fake()->createWithContent('broken.png', substr($png, 0, 33)."\x00\x00\x00\x10IDATnot-image-data!!!!\x00\x00\x00\x00");

    handOver(['signature' => $svg])->assertUnprocessable()->assertJsonValidationErrors('signature');
    handOver(['photo' => proofPhoto('wide.jpg', 4097, 10)])->assertUnprocessable()->assertJsonValidationErrors('photo');
    handOver(['photo' => proofPhoto()->size(5 * 1024 + 1)])->assertUnprocessable()->assertJsonValidationErrors('photo');
    handOver(['photo' => $broken])->assertUnprocessable()->assertJsonValidationErrors('photo');

    expect($this->store->run(static fn (): int => Media::query()->count()))->toBe(0);
    handOver(['photo' => proofPhoto('square.webp', 4096, 4096)], 'delivered-proof-2')->assertOk();
});

it('removes the images\' metadata, turning a photo upright first, and keeps them under their kind\'s name', function (): void {
    handOver(['photo' => jpegWithMetadata('secret-position-6.45N-3.39E')])->assertOk();

    $this->store->run(static function (): void {
        $media = Media::query()->sole();
        $kept = (string) Storage::disk('local')->get($media->getPathRelativeToRoot());
        $size = getimagesizefromstring($kept) ?: [0, 0];

        expect($media->file_name)->toBe('photo.jpg')
            ->and($kept)->not->toContain('secret-position')
            ->and($kept)->not->toContain('Exif')
            // 60 wide by 20 high as taken; turned a quarter, as its camera said to show it.
            ->and([$size[0], $size[1]])->toBe([20, 60]);
    });
});

it('lets staff move a delivery to delivered with proof, and refuses proof with any other move', function (): void {
    $manager = deliveryStaffWith($this->store, ['delivery.manage']);

    deliveryRequest('POST', "deliveries/{$this->delivery}/status", ['status' => 'on_the_way', 'photo' => proofPhoto()], $manager, headers: ['Idempotency-Key' => 'status-proof-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('photo');
    deliveryRequest('POST', "deliveries/{$this->delivery}/status", ['status' => 'delivered', 'recipient_name' => 'Ada Obi'], $manager, headers: ['Idempotency-Key' => 'status-proof-2'])
        ->assertUnprocessable()->assertJsonValidationErrors(['photo', 'signature']);
    deliveryRequest('POST', "deliveries/{$this->delivery}/status", ['status' => 'delivered', 'recipient_name' => 'Ada Obi', 'photo' => proofPhoto(), 'signature' => proofPhoto('signature.png')], $manager, headers: ['Idempotency-Key' => 'status-proof-3'])
        ->assertOk()->assertJsonPath('data.proof', ['photo' => true, 'signature' => true]);
});

it('keeps one set of proof when the same hand-over is sent again', function (): void {
    handOver(['photo' => proofPhoto()], 'delivered-replay')->assertOk();
    handOver(['photo' => proofPhoto()], 'delivered-replay')->assertOk()->assertJsonPath('data.status', 'delivered');

    expect($this->store->run(static fn (): int => Media::query()->count()))->toBe(1)
        ->and(privateStoreFiles())->toHaveCount(1);
});

it('saves the proof with the delivery or not at all, leaving no file behind', function (): void {
    $this->store->run(function (): void {
        // The delivery's own update fails after its proof was stored: the name is longer than its column.
        expect(fn () => app(MoveDelivery::class)->deliver(Delivery::query()->sole(), AccountReference::to($this->driver), str_repeat('a', 256), proofOfDelivery()))
            ->toThrow(QueryException::class)
            ->and(Media::query()->count())->toBe(0)
            ->and(Delivery::query()->sole()->status->value)->toBe('picked_up');
    });

    expect(privateStoreFiles())->toBe([]);
});

it('counts proof towards the plan\'s storage, delivering nothing while there is no room', function (): void {
    storageAllows(1);
    $this->store->run(static fn () => nearlyFullStorage());

    handOver(['photo' => proofPhoto()])->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');

    expect($this->store->run(static fn (): array => [Delivery::query()->sole()->status->value, Media::query()->where('model_type', 'delivery')->count()]))->toBe(['picked_up', 0])
        ->and(privateStoreFiles())->toBe([]);

    storageAllows(null);
    handOver(['photo' => proofPhoto()], 'delivered-proof-2')->assertOk();
});

it('waits for a concurrent upload\'s storage check, so two can\'t both take the last space', function (): void {
    storageAllows(1);

    $this->store->run(function (): void {
        $deliver = fn (): Delivery => app(MoveDelivery::class)->deliver(Delivery::query()->sole(), AccountReference::to($this->driver), 'Ada Obi', proofOfDelivery());

        // Another upload has checked the space and recorded its file, but not yet committed.
        $otherUpload = SecondConnection::open()->holdAdvisoryLock(StorageLimit::LIMIT_KEY);
        $otherUpload->run(static fn () => nearlyFullStorage(SecondConnection::NAME));

        expect($otherUpload->blockedStatement($deliver))->toContain('pg_advisory_xact_lock');

        $otherUpload->commit();

        expect($deliver)->toThrow(UsageLimitReachedException::class)
            ->and(Delivery::query()->sole()->status->value)->toBe('picked_up');
    });
});

it('streams proof only to staff who can view deliveries, never cached', function (): void {
    handOver(['photo' => proofPhoto()])->assertOk();
    $kept = $this->store->run(static fn (): string => (string) Storage::disk('local')->get(Media::query()->sole()->getPathRelativeToRoot()));
    $viewer = deliveryStaffWith($this->store, ['delivery.view']);
    $other = deliveryStaffWith($this->store, ['delivery.manage']);

    $response = deliveryRequest('GET', "deliveries/{$this->delivery}/proof/photo", as: $viewer)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->streamedContent())->toBe($kept)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    deliveryRequest('GET', "deliveries/{$this->delivery}/proof/signature", as: $viewer)->assertNotFound();
    deliveryRequest('GET', "deliveries/{$this->delivery}/proof/receipt", as: $viewer)->assertNotFound();
    deliveryRequest('GET', "deliveries/{$this->delivery}/proof/photo", as: $other)->assertForbidden();
    deliveryRequest('GET', "deliveries/{$this->delivery}/proof/photo")->assertUnauthorized();
    $this->withToken($this->token)->getJson(storeUrl('first-store', "/api/v1/staff/delivery/deliveries/{$this->delivery}/proof/photo"))->assertUnauthorized();
    tenancy()->end();

    ['owner' => $otherOwner] = deliveryStore('second-store');
    deliveryRequest('GET', "deliveries/{$this->delivery}/proof/photo", as: $otherOwner, subdomain: 'second-store')->assertNotFound();
});

it('purges proof files 180 days after the delivery, keeping the delivery and who took the goods', function (): void {
    $this->travelTo(now()->subDays(181));
    handOver(['photo' => proofPhoto()])->assertOk();
    $this->travelBack();

    $recent = assignedDelivery($this->store, $this->owner, $this->driver)->public_id;
    driverAppRequest('POST', "deliveries/{$recent}/pick-up", token: $this->token)->assertOk();
    driverAppRequest('POST', "deliveries/{$recent}/delivered", ['recipient_name' => 'Bola Ade', 'photo' => proofPhoto()], $this->token, headers: ['Idempotency-Key' => 'delivered-recent'])->assertOk();

    $purged = $this->store->run(static fn (): int => app(ProofOfDeliveryRetention::class)->purgeOlderThan(now()->subDays(config()->integer('retention.periods.proof_of_delivery'))->toImmutable()));

    expect($purged)->toBe(1)
        ->and(privateStoreFiles())->toHaveCount(1)
        ->and($this->store->run(fn (): array => [Delivery::query()->where('public_id', $this->delivery)->sole()->recipient_name, Media::query()->sole()->model_id]))
        ->toBe(['Ada Obi', $this->store->run(static fn (): int => Delivery::query()->where('public_id', $recent)->sole()->id)]);
    deliveryRequest('GET', "deliveries/{$this->delivery}/proof/photo", as: $this->owner)->assertNotFound();
});

it('puts proof files in the store export', function (): void {
    handOver(['photo' => proofPhoto()])->assertOk();

    $paths = $this->store->run(static fn (): array => array_map(static fn (ExportedFile $file): string => $file->path, iterator_to_array(app(DeliveryStoreFiles::class)->files(), false)));
    $media = $this->store->run(static fn (): Media => Media::query()->sole());

    expect($paths)->toBe(["delivery/delivery/{$media->model_id}/{$media->id}-photo.jpg"]);
});
