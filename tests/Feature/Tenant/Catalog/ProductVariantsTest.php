<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Catalog\Actions\AddProductVariant;
use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Exceptions\VariantLimitReachedException;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;

/*
 * Section 13: variants differ by store-wide attributes (Size, Colour). A
 * product chooses its options, every variant has exactly one value for each,
 * and no two variants outside the trash share a combination, enforced by the
 * database. A product has at most the configured number of variants. A
 * product with one variant becomes a multi-variant one without losing that
 * variant. Attributes and values in use can't be deleted.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $featureSource);

    $this->store = createStore('variant-store');
    $featureSource->give($this->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['products' => 100]));

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

/**
 * @param  array<string, mixed>  $data
 */
function catalogRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'variant-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'variant-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Creates an attribute with values through the API and returns its ID and the value IDs by English label.
 *
 * @param  list<string>  $labels
 * @return array{id: string, values: array<string, string>}
 */
function attributeWithValues(string $name, array $labels): array
{
    $response = catalogRequest('POST', '/attributes', [
        'name' => ['en' => $name],
        'values' => array_map(static fn (string $label): array => ['en' => $label], $labels),
    ])->assertCreated();

    $values = [];
    foreach ($response->json('data.values') as $value) {
        $values[$value['label']['en']] = $value['id'];
    }

    return ['id' => $response->json('data.id'), 'values' => $values];
}

/**
 * Creates a product with one variant through the API and returns the product and variant IDs.
 *
 * @return array{product: string, variant: string}
 */
function productWithOneVariant(string $sku = 'TEE-1'): array
{
    $response = catalogRequest('POST', '/products', [
        'name' => ['en' => 'Linen shirt'],
        'variant' => ['price' => 1999, 'weight_grams' => 250, 'sku' => $sku],
    ])->assertCreated();

    return ['product' => $response->json('data.id'), 'variant' => $response->json('data.variants.0.id')];
}

it('manages attributes and their values in the store\'s languages', function (): void {
    $size = attributeWithValues('Size', ['S', 'M']);

    catalogRequest('POST', "/attributes/{$size['id']}/values", ['label' => ['en' => 'L']])
        ->assertCreated()
        ->assertJsonPath('data.label', ['en' => 'L'])
        ->assertJsonPath('data.position', 2);
    catalogRequest('PATCH', "/attributes/{$size['id']}", ['name' => ['en' => 'Shirt size']])->assertOk()->assertJsonPath('data.name.en', 'Shirt size');
    catalogRequest('PATCH', "/attributes/{$size['id']}/values/{$size['values']['S']}", ['label' => ['en' => 'Small']])->assertOk()->assertJsonPath('data.label.en', 'Small');

    catalogRequest('GET', '/attributes', as: $this->viewer)
        ->assertOk()
        ->assertJsonPath('data.0.name.en', 'Shirt size')
        ->assertJsonPath('data.0.values.*.label.en', ['Small', 'M', 'L']);

    catalogRequest('POST', '/attributes', ['name' => ['fr' => 'Taille']])->assertUnprocessable()->assertJsonValidationErrors('name');
    catalogRequest('POST', '/attributes', ['name' => ['en' => 'Colour']], as: $this->viewer)->assertForbidden();
    catalogRequest('DELETE', "/attributes/{$size['id']}/values/{$size['values']['M']}", as: $this->viewer)->assertForbidden();

    // A value of another attribute is not found under this one.
    $colour = attributeWithValues('Colour', ['Blue']);
    catalogRequest('PATCH', "/attributes/{$size['id']}/values/{$colour['values']['Blue']}", ['label' => ['en' => 'X']])->assertNotFound();

    catalogRequest('DELETE', "/attributes/{$size['id']}/values/{$size['values']['M']}")->assertNoContent();
    catalogRequest('DELETE', "/attributes/{$colour['id']}")->assertNoContent();
    expect($this->store->run(static fn (): array => [Attribute::query()->count(), AttributeValue::query()->count()]))->toBe([1, 2]);
});

it('turns a product with one variant into one with many, keeping that variant', function (): void {
    $size = attributeWithValues('Size', ['S', 'M', 'L']);
    $colour = attributeWithValues('Colour', ['Blue', 'Red']);
    ['product' => $product, 'variant' => $first] = productWithOneVariant();

    catalogRequest('PUT', "/products/{$product}/options", [
        'attributes' => [$size['id'], $colour['id']],
        'variants' => [['id' => $first, 'values' => [$colour['values']['Blue'], $size['values']['S']]]],
    ])
        ->assertOk()
        ->assertJsonPath('data.options.*.id', [$size['id'], $colour['id']])
        ->assertJsonPath('data.variants.0.id', $first)
        ->assertJsonPath('data.variants.0.sku', 'TEE-1')
        ->assertJsonPath('data.variants.0.price.amount', 1999)
        ->assertJsonCount(2, 'data.variants.0.values');

    catalogRequest('POST', "/products/{$product}/variants", [
        'values' => [$size['values']['M'], $colour['values']['Blue']],
        'price' => 2099,
        'weight_grams' => 260,
        'sku' => 'TEE-2',
    ])
        ->assertCreated()
        ->assertJsonPath('data.sku', 'TEE-2')
        ->assertJsonPath('data.position', 1)
        ->assertJsonPath('data.price.currency', 'NGN')
        ->assertJsonPath('data.values.*.value_id', [$size['values']['M'], $colour['values']['Blue']]);

    catalogRequest('GET', "/products/{$product}")->assertOk()->assertJsonCount(2, 'data.variants');

    $audits = $this->store->run(static fn (): array => Audit::query()->where('event', 'sync')->pluck('auditable_type')->sort()->values()->all());
    expect($audits)->toBe(['product', 'product_variant', 'product_variant']);
});

it('never lets two variants outside the trash share a combination', function (): void {
    $size = attributeWithValues('Size', ['S', 'M']);
    ['product' => $product, 'variant' => $first] = productWithOneVariant();
    catalogRequest('PUT', "/products/{$product}/options", ['attributes' => [$size['id']], 'variants' => [['id' => $first, 'values' => [$size['values']['S']]]]])->assertOk();

    catalogRequest('POST', "/products/{$product}/variants", ['values' => [$size['values']['S']], 'weight_grams' => 100])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'variant_combination_taken')
        ->assertJsonValidationErrors('values');

    // The database refuses it too, even when the check in code is skipped; a variant in the trash doesn't count.
    $this->store->run(static function (): void {
        $existing = ProductVariant::query()->sole();
        $duplicate = static fn (): ProductVariant => ProductVariant::factory()->create(['product_id' => $existing->product_id, 'attribute_signature' => $existing->attribute_signature]);

        expect($duplicate)->toThrow(UniqueConstraintViolationException::class);

        $existing->delete();
        expect($duplicate()->exists)->toBeTrue();
    });
});

it('gives every variant exactly one value for each option, and refuses incomplete or colliding options', function (): void {
    $size = attributeWithValues('Size', ['S', 'M']);
    $colour = attributeWithValues('Colour', ['Blue']);
    ['product' => $product, 'variant' => $first] = productWithOneVariant();

    // A variant without options of its own can't be joined by another.
    catalogRequest('POST', "/products/{$product}/variants", ['values' => [$size['values']['M']], 'weight_grams' => 100])
        ->assertConflict()
        ->assertJsonPath('code', 'product_has_no_options');

    $options = static fn (array $attributes, array $variants): TestResponse => catalogRequest('PUT', "/products/{$product}/options", ['attributes' => $attributes, 'variants' => $variants]);

    $options([$size['id']], [['id' => $first, 'values' => [$colour['values']['Blue']]]])->assertUnprocessable()->assertJsonPath('code', 'product_options_incomplete');
    $options([$size['id'], $colour['id']], [['id' => $first, 'values' => [$size['values']['S']]]])->assertUnprocessable()->assertJsonPath('code', 'product_options_incomplete');
    $options([$size['id']], [['id' => $first, 'values' => [$size['values']['S'], $size['values']['M']]]])->assertUnprocessable()->assertJsonPath('code', 'product_options_incomplete');
    $options([$size['id']], [['id' => 'not-a-variant', 'values' => [$size['values']['S']]]])->assertUnprocessable()->assertJsonValidationErrors('variants.0.id');
    $options([$size['id']], [['id' => $first, 'values' => [$size['values']['S']]]])->assertOk();

    $second = catalogRequest('POST', "/products/{$product}/variants", ['values' => [$size['values']['M']], 'weight_grams' => 100])->assertCreated()->json('data.id');
    catalogRequest('POST', "/products/{$product}/variants", ['values' => [$size['values']['M'], $colour['values']['Blue']], 'weight_grams' => 100])
        ->assertUnprocessable()->assertJsonPath('code', 'product_options_incomplete');

    // Every variant outside the trash must be listed, and no two may end up alike.
    $options([$size['id']], [['id' => $first, 'values' => [$size['values']['M']]]])->assertUnprocessable()->assertJsonPath('code', 'product_options_incomplete');
    $options([], [['id' => $first, 'values' => []], ['id' => $second, 'values' => []]])->assertUnprocessable()->assertJsonPath('code', 'variant_combination_taken');

    // Swapping combinations works.
    $options([$size['id']], [['id' => $first, 'values' => [$size['values']['M']]], ['id' => $second, 'values' => [$size['values']['S']]]])
        ->assertOk()
        ->assertJsonPath('data.variants.0.values.0.value_id', $size['values']['M'])
        ->assertJsonPath('data.variants.1.values.0.value_id', $size['values']['S']);
});

it('refuses to delete an attribute or value that a product or variant uses, even one in the trash', function (): void {
    $size = attributeWithValues('Size', ['S', 'M']);
    ['product' => $product, 'variant' => $first] = productWithOneVariant();
    catalogRequest('PUT', "/products/{$product}/options", ['attributes' => [$size['id']], 'variants' => [['id' => $first, 'values' => [$size['values']['S']]]]])->assertOk();

    catalogRequest('DELETE', "/attributes/{$size['id']}")->assertConflict()->assertJsonPath('code', 'attribute_in_use');
    catalogRequest('DELETE', "/attributes/{$size['id']}/values/{$size['values']['S']}")->assertConflict()->assertJsonPath('code', 'attribute_value_in_use');
    catalogRequest('DELETE', "/attributes/{$size['id']}/values/{$size['values']['M']}")->assertNoContent();

    $this->store->run(static fn () => ProductVariant::query()->sole()->delete());
    catalogRequest('DELETE', "/attributes/{$size['id']}/values/{$size['values']['S']}")->assertConflict()->assertJsonPath('code', 'attribute_value_in_use');
});

it('caps how many variants a product has, from config', function (): void {
    config(['catalog.max_variants_per_product' => 2]);
    $size = attributeWithValues('Size', ['S', 'M', 'L']);
    ['product' => $product, 'variant' => $first] = productWithOneVariant();
    catalogRequest('PUT', "/products/{$product}/options", ['attributes' => [$size['id']], 'variants' => [['id' => $first, 'values' => [$size['values']['S']]]]])->assertOk();

    catalogRequest('POST', "/products/{$product}/variants", ['values' => [$size['values']['M']], 'weight_grams' => 100])->assertCreated();
    catalogRequest('POST', "/products/{$product}/variants", ['values' => [$size['values']['L']], 'weight_grams' => 100])
        ->assertConflict()
        ->assertJsonPath('code', 'variant_limit_reached');

    // A variant in the trash doesn't count.
    $this->store->run(static fn () => ProductVariant::query()->whereHas('attributeValues', static fn ($query) => $query->where('attribute_values.public_id', $size['values']['M']))->sole()->delete());
    catalogRequest('POST', "/products/{$product}/variants", ['values' => [$size['values']['L']], 'weight_grams' => 100])->assertCreated();
});

it('waits for a concurrent request adding a variant, so two can\'t both take the last place', function (): void {
    config(['catalog.max_variants_per_product' => 2]);
    $size = attributeWithValues('Size', ['S', 'M', 'L']);
    ['product' => $product, 'variant' => $first] = productWithOneVariant();
    catalogRequest('PUT', "/products/{$product}/options", ['attributes' => [$size['id']], 'variants' => [['id' => $first, 'values' => [$size['values']['S']]]]])->assertOk();

    $this->store->run(static function () use ($size): void {
        $product = Product::query()->sole();
        $large = AttributeValue::query()->where('public_id', $size['values']['L'])->sole();
        $addVariant = static fn (): ProductVariant => app(AddProductVariant::class)->handle($product, [$large], new ProductVariantData(weightGrams: 100));

        // The other request has added the product's last variant, but not yet committed. Adding a variant
        // only takes a key-share lock on the product, so without our own lock both requests would count 1.
        $otherRequest = SecondConnection::open();
        $otherRequest->run(static fn () => ProductVariant::factory()->connection(SecondConnection::NAME)->create(['product_id' => $product->id, 'attribute_signature' => 'other']));

        expect($otherRequest->blocks($addVariant))->toBeTrue();

        $otherRequest->commit();

        expect($addVariant)->toThrow(VariantLimitReachedException::class)
            ->and(ProductVariant::query()->count())->toBe(2);
    });
});

it('keeps attributes inside their own store', function (): void {
    $this->otherStore = createStore('other-variant-store');
    $otherOwner = $this->otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $size = attributeWithValues('Size', ['S']);

    catalogRequest('GET', '/attributes', as: $otherOwner, subdomain: 'other-variant-store')->assertOk()->assertJsonCount(0, 'data');
    catalogRequest('GET', "/attributes/{$size['id']}", as: $otherOwner, subdomain: 'other-variant-store')->assertNotFound();
});
