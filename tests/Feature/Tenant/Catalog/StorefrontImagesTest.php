<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Shared\Features\StorageLimit;
use App\Shared\Media\StorefrontImage;
use App\Shared\Media\StorefrontImageUploader;
use App\Shared\Privacy\ExportedFile;
use App\Shared\Privacy\StoreExportRegistry;
use App\Tenant\Catalog\CatalogStoreFiles;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\SettingsStoreFiles;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;

/*
 * Sections 8 and 14: storefront images (product gallery, variant image,
 * category image, brand logo, store logo) go on the public disk only, are
 * JPEG, PNG or WebP (never SVG) within a size and maximum dimensions, count
 * towards the plan's storage, are served on the store's own domain, and go
 * into the store export as uploaded.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);

    $this->store = createStore('image-store');
    imageStoreLimits(storageInMegabytes: 100);

    [$this->owner, $this->viewer] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $viewerRole = Role::findOrCreate('Catalog viewer', StaffMember::GUARD);
        $viewerRole->givePermissionTo(Permission::findOrCreate('catalog.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($viewerRole);

        return [$owner, $viewer];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

function imageStoreLimits(?int $storageInMegabytes): void
{
    test()->featureSource->give(test()->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['products' => 100, 'storage_in_megabytes' => $storageInMegabytes]));
    app(Features::class)->forget(test()->store->getTenantKey());
}

/**
 * @param  array<string, mixed>  $data
 */
function imageRequest(string $method, string $path, array $data = [], ?StaffMember $as = null): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $url = storeUrl('image-store', '/api/v1/staff'.$path);
    // Uploads go as multipart form data; everything else as JSON.
    $response = $method === 'POST'
        ? test()->withToken($token)->post($url, $data, ['Accept' => 'application/json'])
        : test()->withToken($token)->json($method, $url, $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @return array{product: string, variant: string}
 */
function imageProduct(): array
{
    $response = imageRequest('POST', '/catalog/products', ['name' => ['en' => 'Linen shirt'], 'variant' => ['price' => 1999, 'weight_grams' => 250]])->assertCreated();

    return ['product' => $response->json('data.id'), 'variant' => $response->json('data.variants.0.id')];
}

it('keeps a product\'s gallery on the public disk, in order, served on the store\'s own domain', function (): void {
    ['product' => $product] = imageProduct();

    $front = imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('front.jpg', 800, 600)])
        ->assertCreated()
        ->assertJsonPath('data.width', 800)
        ->assertJsonPath('data.height', 600)
        ->json('data');
    $back = imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('back.png', 400, 400)])->assertCreated()->json('data.id');

    expect($front['url'])->toStartWith(storeUrl('image-store', '/tenancy/assets/'));

    $shown = imageRequest('GET', "/catalog/products/{$product}")
        ->assertOk()
        ->assertJsonPath('data.images.*.id', [$front['id'], $back])
        ->json('data.images.0');
    expect($shown['thumbnail_url'])->toEndWith('.webp')
        ->and($shown['large_url'])->toEndWith('.webp');

    $this->get($front['url'])->assertOk();
    $this->get($shown['thumbnail_url'])->assertOk();

    $this->store->run(static function (): void {
        $media = Media::query()->orderBy('id')->get();

        expect($media->pluck('disk')->unique()->all())->toBe([StorefrontImage::DISK])
            ->and(Storage::disk('public')->exists($media[0]->getPathRelativeToRoot()))->toBeTrue()
            // Tenancy roots the private disk one level above the public folder: nothing is kept outside it.
            ->and(array_filter(Storage::disk('local')->allFiles(), static fn (string $path): bool => ! str_starts_with($path, 'public/')))->toBe([]);
    });

    imageRequest('PUT', "/catalog/products/{$product}/images/order", ['images' => [$back, $front['id']]])->assertOk()->assertJsonPath('data.images.*.id', [$back, $front['id']]);
    imageRequest('PUT', "/catalog/products/{$product}/images/order", ['images' => [$back]])->assertUnprocessable()->assertJsonPath('code', 'product_image_order_mismatch');

    imageRequest('DELETE', "/catalog/products/{$product}/images/{$back}")->assertNoContent();
    imageRequest('DELETE', "/catalog/products/{$product}/images/{$back}")->assertNotFound();
    imageRequest('GET', "/catalog/products/{$product}")->assertJsonPath('data.images.*.id', [$front['id']]);
});

it('keeps one store\'s images away from another store', function (): void {
    ['product' => $product] = imageProduct();
    $other = createStore('other-image-store');
    imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('front.jpg', 100, 100)])->assertCreated();

    $path = $this->store->run(static fn (): string => Media::query()->sole()->getPathRelativeToRoot());

    expect($this->store->run(static fn (): bool => Storage::disk('public')->exists($path)))->toBeTrue()
        ->and($other->run(static fn (): bool => Storage::disk('public')->exists($path)))->toBeFalse();
});

it('shows a variant one of its own product\'s gallery images, and none once that image is deleted', function (): void {
    ['product' => $product, 'variant' => $variant] = imageProduct();
    ['product' => $otherProduct] = imageProduct();
    $image = imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('blue.jpg', 100, 100)])->assertCreated()->json('data.id');
    $otherImage = imageRequest('POST', "/catalog/products/{$otherProduct}/images", ['image' => UploadedFile::fake()->image('red.jpg', 100, 100)])->assertCreated()->json('data.id');

    imageRequest('PATCH', "/catalog/products/{$product}/variants/{$variant}", ['image' => $otherImage])->assertUnprocessable()->assertJsonValidationErrors('image');
    imageRequest('PATCH', "/catalog/products/{$product}/variants/{$variant}", ['image' => $image])->assertOk()->assertJsonPath('data.image_id', $image);

    imageRequest('DELETE', "/catalog/products/{$product}/images/{$image}")->assertNoContent();
    imageRequest('GET', "/catalog/products/{$product}")->assertJsonPath('data.variants.0.image_id', null);

    $image = imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('blue.jpg', 100, 100)])->assertCreated()->json('data.id');
    imageRequest('PATCH', "/catalog/products/{$product}/variants/{$variant}", ['image' => $image])->assertOk();
    imageRequest('PATCH', "/catalog/products/{$product}/variants/{$variant}", ['image' => null])->assertOk()->assertJsonPath('data.image_id', null);
});

it('accepts only JPEG, PNG and WebP images within the size and dimension limits', function (): void {
    ['product' => $product] = imageProduct();
    $upload = static fn (UploadedFile $file): TestResponse => imageRequest('POST', "/catalog/products/{$product}/images", ['image' => $file]);

    $upload(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertUnprocessable()->assertJsonValidationErrors('image');
    $upload(UploadedFile::fake()->create('brochure.pdf', 10, 'application/pdf'))->assertUnprocessable()->assertJsonValidationErrors('image');
    $upload(UploadedFile::fake()->image('wide.png', 6001, 10))->assertUnprocessable()->assertJsonValidationErrors('image');
    $upload(UploadedFile::fake()->image('front.webp', 6000, 10))->assertCreated();

    config(['media.storefront_images.max_kilobytes' => 1]);
    $upload(UploadedFile::fake()->image('heavy.jpg', 600, 600)->size(2))->assertUnprocessable()->assertJsonValidationErrors('image');

    imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('front.jpg', 10, 10)], as: $this->viewer)->assertForbidden();
});

it('counts images towards the plan\'s storage', function (): void {
    ['product' => $product] = imageProduct();
    imageStoreLimits(storageInMegabytes: 1);
    $this->store->run(static fn () => DB::table('media')->insert([
        'model_type' => 'product', 'model_id' => Product::query()->sole()->id, 'uuid' => (string) Str::uuid(), 'collection_name' => 'gallery',
        'name' => 'big', 'file_name' => 'big.jpg', 'mime_type' => 'image/jpeg', 'disk' => 'public', 'size' => 1024 * 1024 - 100,
        'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]',
    ]));

    imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('front.jpg', 100, 100)])
        ->assertForbidden()
        ->assertJsonPath('code', 'usage_limit_reached');

    imageStoreLimits(storageInMegabytes: null);
    imageRequest('POST', "/catalog/products/{$product}/images", ['image' => UploadedFile::fake()->image('front.jpg', 100, 100)])->assertCreated();
});

it('waits for a concurrent upload, so two can\'t both take the last space', function (): void {
    imageProduct();
    imageStoreLimits(storageInMegabytes: 1);

    $this->store->run(static function (): void {
        $product = Product::query()->sole();
        $upload = static fn (): Media => app(StorefrontImageUploader::class)->add($product, UploadedFile::fake()->image('front.jpg', 100, 100), Product::GALLERY);

        // The other upload has checked the space and recorded its file, but not yet committed.
        $otherUpload = SecondConnection::open()->holdAdvisoryLock(StorageLimit::LIMIT_KEY);
        $otherUpload->run(static fn () => DB::connection(SecondConnection::NAME)->table('media')->insert([
            'model_type' => 'product', 'model_id' => $product->id, 'uuid' => (string) Str::uuid(), 'collection_name' => 'gallery',
            'name' => 'big', 'file_name' => 'big.jpg', 'mime_type' => 'image/jpeg', 'disk' => 'public', 'size' => 1024 * 1024 - 100,
            'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]',
        ]));

        expect($otherUpload->blocks($upload))->toBeTrue();

        $otherUpload->commit();

        expect($upload)->toThrow(UsageLimitReachedException::class)
            ->and(Media::query()->count())->toBe(1);
    });
});

it('keeps one logo per brand, one image per category and one store logo, replacing the old file', function (): void {
    $brand = imageRequest('POST', '/catalog/brands', ['name' => ['en' => 'Ada Fabrics']])->assertCreated()->json('data.id');
    $category = imageRequest('POST', '/catalog/categories', ['name' => ['en' => 'Shirts']])->assertCreated()->json('data.id');

    $first = imageRequest('POST', "/catalog/brands/{$brand}/logo", ['image' => UploadedFile::fake()->image('logo.png', 200, 100)])->assertOk()->json('data.logo');
    $second = imageRequest('POST', "/catalog/brands/{$brand}/logo", ['image' => UploadedFile::fake()->image('logo-2.png', 200, 100)])->assertOk()->json('data.logo');
    expect($second['id'])->not->toBe($first['id']);
    imageRequest('GET', "/catalog/brands/{$brand}")->assertJsonPath('data.logo.id', $second['id']);

    imageRequest('POST', "/catalog/categories/{$category}/image", ['image' => UploadedFile::fake()->image('shirts.jpg', 300, 200)])->assertOk()->assertJsonPath('data.image.width', 300);
    imageRequest('POST', '/store/settings/logo', ['logo' => UploadedFile::fake()->image('store.png', 120, 60)])->assertOk()->assertJsonPath('data.logo.height', 60);

    expect($this->store->run(static fn (): array => Media::query()->orderBy('collection_name')->pluck('collection_name')->all()))->toBe(['image', 'logo', 'logo']);

    imageRequest('DELETE', "/catalog/brands/{$brand}/logo")->assertNoContent();
    imageRequest('DELETE', "/catalog/categories/{$category}/image")->assertNoContent();
    imageRequest('DELETE', '/store/settings/logo')->assertNoContent();
    imageRequest('GET', "/catalog/brands/{$brand}")->assertJsonPath('data.logo', null);
    imageRequest('GET', '/store/settings')->assertJsonPath('data.logo', null);

    expect($this->store->run(static fn (): array => [Media::query()->count(), Storage::disk('public')->allFiles()]))->toBe([0, []]);
});

it('puts every catalog image and the store logo into the store export as uploaded', function (): void {
    ['product' => $product] = imageProduct();
    $front = UploadedFile::fake()->image('front.jpg', 50, 50);
    $uploadedBytes = (string) file_get_contents($front->getRealPath());
    imageRequest('POST', "/catalog/products/{$product}/images", ['image' => $front])->assertCreated();
    imageRequest('POST', '/store/settings/logo', ['logo' => UploadedFile::fake()->image('store.png', 50, 50)])->assertOk();

    expect(app(StoreExportRegistry::class)->fileSources())->toContain(CatalogStoreFiles::class)->toContain(SettingsStoreFiles::class);

    $this->store->run(static function () use ($uploadedBytes): void {
        $catalogFiles = [...app(CatalogStoreFiles::class)->files()];
        $settingsFiles = [...app(SettingsStoreFiles::class)->files()];

        expect($catalogFiles)->toHaveCount(1)
            ->and($catalogFiles[0]->path)->toMatch('#^catalog/product/\d+/\d+-front\.jpg$#')
            ->and(stream_get_contents(($catalogFiles[0]->openStream)()))->toBe($uploadedBytes)
            ->and(array_map(static fn (ExportedFile $file): string => $file->path, $settingsFiles))->toHaveCount(1);
    });
});
