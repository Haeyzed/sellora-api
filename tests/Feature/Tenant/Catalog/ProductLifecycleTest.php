<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Features;
use App\Tenant\Catalog\Actions\ArchiveProduct;
use App\Tenant\Catalog\Actions\FindProductVariant;
use App\Tenant\Catalog\Actions\PublishProduct;
use App\Tenant\Catalog\Events\ProductArchived;
use App\Tenant\Catalog\Events\ProductPublished;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
 * Sections 3.3 and 13: a product is published only with a name in the
 * store's default language and at least one priced variant; archiving hides
 * it; the trash keeps it (with its variants) and frees its slug and SKUs;
 * restoring re-checks the plan's limit and every conflict. Events are
 * announced only after the change is saved. Old orders always find a
 * variant, even in the trash.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);

    $this->store = createStore('lifecycle-store');
    lifecycleProductLimit(100);

    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

function lifecycleProductLimit(int $limit): void
{
    test()->featureSource->give(test()->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['products' => $limit]));
    app(Features::class)->forget(test()->store->getTenantKey());
}

/**
 * @param  array<string, mixed>  $data
 */
function lifecycleRequest(string $method, string $path, array $data = []): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue(test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('lifecycle-store', '/api/v1/staff/catalog'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @param  array<string, mixed>  $variant
 * @return array{product: string, variant: string}
 */
function lifecycleProduct(string $name = 'Linen shirt', array $variant = ['price' => 1999]): array
{
    $response = lifecycleRequest('POST', '/products', ['name' => ['en' => $name], 'variant' => ['weight_grams' => 250, ...$variant]])->assertCreated();

    return ['product' => $response->json('data.id'), 'variant' => $response->json('data.variants.0.id')];
}

it('publishes a product only with a default-language name and a priced variant', function (): void {
    Event::fake([ProductPublished::class]);
    ['product' => $product, 'variant' => $variant] = lifecycleProduct(variant: []);

    lifecycleRequest('POST', "/products/{$product}/publish")
        ->assertConflict()
        ->assertJsonPath('code', 'product_cannot_be_published')
        ->assertJsonValidationErrors('variants')
        ->assertJsonMissingValidationErrors('name');

    lifecycleRequest('PATCH', "/products/{$product}/variants/{$variant}", ['price' => 1500])->assertOk();
    $this->store->run(static fn () => Product::query()->sole()->setTranslation('name', 'fr', 'Chemise en lin')->forgetTranslation('name', 'en')->save());

    lifecycleRequest('POST', "/products/{$product}/publish")
        ->assertConflict()
        ->assertJsonValidationErrors('name')
        ->assertJsonMissingValidationErrors('variants');
    Event::assertNotDispatched(ProductPublished::class);

    $this->store->run(static fn () => Product::query()->sole()->setTranslation('name', 'en', 'Linen shirt')->save());
    lifecycleRequest('POST', "/products/{$product}/publish")->assertOk()->assertJsonPath('data.status', 'active');
    lifecycleRequest('POST', "/products/{$product}/publish")->assertConflict()->assertJsonPath('code', 'product_status_conflict');

    Event::assertDispatchedTimes(ProductPublished::class, 1);
});

it('announces publishing and archiving only once the change is saved', function (): void {
    ['product' => $productId] = lifecycleProduct();
    $announced = [];
    Event::listen(ProductPublished::class, static function (ProductPublished $event) use (&$announced): void {
        $announced[] = 'published '.$event->product->public_id;
    });
    Event::listen(ProductArchived::class, static function (ProductArchived $event) use (&$announced): void {
        $announced[] = 'archived '.$event->product->public_id;
    });

    $this->store->run(static function () use (&$announced): void {
        DB::beginTransaction();
        app(PublishProduct::class)->handle(Product::query()->sole());
        expect($announced)->toBe([]);
        DB::rollBack();
    });
    expect($announced)->toBe([]);

    lifecycleRequest('POST', "/products/{$productId}/publish")->assertOk();

    $this->store->run(static function () use (&$announced): void {
        DB::beginTransaction();
        app(ArchiveProduct::class)->handle(Product::query()->sole());
        expect($announced)->toHaveCount(1);
        DB::rollBack();
    });
    expect($announced)->toHaveCount(1);

    lifecycleRequest('POST', "/products/{$productId}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
    lifecycleRequest('POST', "/products/{$productId}/archive")->assertConflict()->assertJsonPath('code', 'product_status_conflict');
    lifecycleRequest('POST', "/products/{$productId}/publish")->assertOk()->assertJsonPath('data.status', 'active');

    expect($announced)->toBe(["published {$productId}", "archived {$productId}", "published {$productId}"]);
});

it('trashes a product with its variants, freeing its slug and SKUs, and restores it as a draft once nothing conflicts', function (): void {
    Event::fake([ProductArchived::class]);
    $size = lifecycleRequest('POST', '/attributes', ['name' => ['en' => 'Size'], 'values' => [['en' => 'S'], ['en' => 'M']]])->assertCreated()->json('data');
    ['product' => $product, 'variant' => $small] = lifecycleProduct(variant: ['price' => 1999, 'sku' => 'LIN-S']);
    lifecycleRequest('PUT', "/products/{$product}/options", ['attributes' => [$size['id']], 'variants' => [['id' => $small, 'values' => [$size['values'][0]['id']]]]])->assertOk();
    $medium = lifecycleRequest('POST', "/products/{$product}/variants", ['values' => [$size['values'][1]['id']], 'weight_grams' => 250, 'sku' => 'LIN-M'])->assertCreated()->json('data.id');
    lifecycleRequest('POST', "/products/{$product}/publish")->assertOk();

    // A variant trashed on its own stays in the trash when the product comes back.
    lifecycleRequest('DELETE', "/products/{$product}/variants/{$medium}")->assertNoContent();
    lifecycleRequest('DELETE', "/products/{$product}")->assertNoContent();
    Event::assertDispatched(ProductArchived::class);

    expect($this->store->run(static fn (): array => [Product::query()->count(), ProductVariant::query()->count()]))->toBe([0, 0]);
    lifecycleRequest('GET', "/products/{$product}")->assertOk()->assertJsonPath('data.variants', []);
    lifecycleRequest('PATCH', "/products/{$product}", ['name' => ['en' => 'Changed']])->assertNotFound();

    // Its slug and SKU are free for others meanwhile, so it can't come back until they are freed again.
    $other = lifecycleRequest('POST', '/products', ['name' => ['en' => 'Linen shirt'], 'slug' => 'linen-shirt', 'variant' => ['weight_grams' => 100, 'sku' => 'LIN-S']])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'linen-shirt')
        ->json('data.id');
    lifecycleRequest('POST', "/products/{$product}/restore")->assertConflict()->assertJsonPath('code', 'product_restore_conflict');
    expect($this->store->run(static fn (): int => Product::onlyTrashed()->count()))->toBe(1);

    lifecycleRequest('PATCH', "/products/{$other}", ['slug' => 'linen-shirt-two'])->assertOk();
    lifecycleRequest('POST', "/products/{$product}/restore")->assertConflict()->assertJsonPath('code', 'product_restore_conflict');

    lifecycleRequest('DELETE', "/products/{$other}")->assertNoContent();
    lifecycleRequest('POST', "/products/{$product}/restore")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.trashed_at', null)
        ->assertJsonPath('data.variants.*.id', [$small]);
    lifecycleRequest('POST', "/products/{$product}/restore")->assertOk();
});

it('re-checks the plan\'s product limit when restoring', function (): void {
    ['product' => $first] = lifecycleProduct('First');
    lifecycleRequest('DELETE', "/products/{$first}")->assertNoContent();
    lifecycleProductLimit(1);
    lifecycleProduct('Second');

    lifecycleRequest('POST', "/products/{$first}/restore")->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');
    expect($this->store->run(static fn (): int => Product::onlyTrashed()->count()))->toBe(1);
});

it('trashes and restores single variants, re-checking that they still fit the product', function (): void {
    config(['catalog.max_variants_per_product' => 3]);
    $size = lifecycleRequest('POST', '/attributes', ['name' => ['en' => 'Size'], 'values' => [['en' => 'S'], ['en' => 'M'], ['en' => 'L'], ['en' => 'XL']]])->assertCreated()->json('data.values.*.id');
    $colour = lifecycleRequest('POST', '/attributes', ['name' => ['en' => 'Colour'], 'values' => [['en' => 'Blue']]])->assertCreated()->json('data');
    ['product' => $product, 'variant' => $small] = lifecycleProduct(variant: ['price' => 1999, 'sku' => 'LIN-S']);
    $sizeAttribute = lifecycleRequest('GET', '/attributes')->json('data.0.id');

    lifecycleRequest('DELETE', "/products/{$product}/variants/{$small}")->assertConflict()->assertJsonPath('code', 'product_needs_a_variant');

    lifecycleRequest('PUT', "/products/{$product}/options", ['attributes' => [$sizeAttribute], 'variants' => [['id' => $small, 'values' => [$size[0]]]]])->assertOk();
    $medium = lifecycleRequest('POST', "/products/{$product}/variants", ['values' => [$size[1]], 'weight_grams' => 250, 'sku' => 'LIN-M'])->assertCreated()->json('data.id');

    lifecycleRequest('DELETE', "/products/{$product}/variants/{$medium}")->assertNoContent();
    lifecycleRequest('POST', "/products/{$product}/variants/{$medium}/restore")->assertOk()->assertJsonPath('data.trashed_at', null);

    // Its combination taken again meanwhile: refused.
    lifecycleRequest('DELETE', "/products/{$product}/variants/{$medium}")->assertNoContent();
    $mediumAgain = lifecycleRequest('POST', "/products/{$product}/variants", ['values' => [$size[1]], 'weight_grams' => 250])->assertCreated()->json('data.id');
    lifecycleRequest('POST', "/products/{$product}/variants/{$medium}/restore")->assertConflict()->assertJsonPath('code', 'variant_restore_conflict');

    // The product full: refused.
    lifecycleRequest('DELETE', "/products/{$product}/variants/{$mediumAgain}")->assertNoContent();
    $large = lifecycleRequest('POST', "/products/{$product}/variants", ['values' => [$size[2]], 'weight_grams' => 250])->assertCreated()->json('data.id');
    $extraLarge = lifecycleRequest('POST', "/products/{$product}/variants", ['values' => [$size[3]], 'weight_grams' => 250])->assertCreated()->json('data.id');
    lifecycleRequest('POST', "/products/{$product}/variants/{$medium}/restore")->assertConflict()->assertJsonPath('code', 'variant_limit_reached');

    // The product's options changed since: its values no longer fit.
    lifecycleRequest('DELETE', "/products/{$product}/variants/{$large}")->assertNoContent();
    lifecycleRequest('DELETE', "/products/{$product}/variants/{$extraLarge}")->assertNoContent();
    lifecycleRequest('PUT', "/products/{$product}/options", ['attributes' => [$sizeAttribute, $colour['id']], 'variants' => [['id' => $small, 'values' => [$size[0], $colour['values'][0]['id']]]]])->assertOk();
    lifecycleRequest('POST', "/products/{$product}/variants/{$medium}/restore")->assertConflict()->assertJsonPath('code', 'variant_restore_conflict');

    // The product itself in the trash: restore the product instead.
    lifecycleRequest('DELETE', "/products/{$product}")->assertNoContent();
    lifecycleRequest('POST', "/products/{$product}/variants/{$small}/restore")->assertConflict()->assertJsonPath('code', 'product_status_conflict');
});

it('lets old orders find a variant even when it and its product are in the trash', function (): void {
    ['product' => $product, 'variant' => $variant] = lifecycleProduct(variant: ['price' => 1999, 'sku' => 'LIN-1']);
    lifecycleRequest('DELETE', "/products/{$product}")->assertNoContent();

    $this->store->run(static function () use ($variant, $product): void {
        $found = app(FindProductVariant::class)->handle($variant);

        expect($found)->not->toBeNull()
            ->and($found?->trashed())->toBeTrue()
            ->and($found?->sku)->toBe('LIN-1')
            ->and($found?->price?->minorAmount())->toBe(1999)
            ->and($found?->product->public_id)->toBe($product)
            ->and($found?->product->trashed())->toBeTrue()
            ->and($found?->relationLoaded('attributeValues'))->toBeTrue()
            ->and(app(FindProductVariant::class)->handle('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->toBeNull();
    });
});
