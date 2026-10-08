<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Actions\CreateProduct;
use App\Tenant\Catalog\Data\ProductData;
use App\Tenant\Catalog\Data\ProductVariantData;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Catalog\Services\ProductLimit;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;

/*
 * Sections 3.3, 8, 11 and 13: a product starts as a draft with its first
 * variant. Prices are integer minor units in the store's base currency,
 * which then locks; a compare-at price is above the price; whatever ships
 * has a weight in grams, and dimensions are millimetres, all or none. SKUs
 * and slugs are unique outside the trash. The primary category is always
 * one of the product's categories. The plan limits products.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);

    $this->store = createStore('product-store');
    allowProducts($this->store->getTenantKey(), 100);

    [$this->owner, $this->viewer] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $viewerRole = Role::findOrCreate('Catalog viewer', StaffMember::GUARD);
        $viewerRole->givePermissionTo(Permission::findOrCreate('catalog.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($viewerRole);

        return [$owner, $viewer];
    });

    [$this->brand, $this->shirts, $this->linen] = $this->store->run(static fn (): array => [
        Brand::factory()->create(['name' => ['en' => 'Ada Fabrics']]),
        Category::factory()->create(['name' => ['en' => 'Shirts']]),
        Category::factory()->create(['name' => ['en' => 'Linen']]),
    ]);
});

afterEach(function (): void {
    deleteAllStores();
});

function allowProducts(string $tenantId, int $limit): void
{
    test()->featureSource->give($tenantId, FakeFeatureSource::snapshot(limits: ['products' => $limit]));
    app(Features::class)->forget($tenantId);
}

/**
 * @param  array<string, mixed>  $data
 */
function productRequest(string $method, string $path = '', array $data = [], ?StaffMember $as = null, string $subdomain = 'product-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'product-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog/products'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  array<string, mixed>  $variant
 * @return array<string, mixed>
 */
function newProduct(array $overrides = [], array $variant = []): array
{
    return [
        'name' => ['en' => 'Linen shirt'],
        'variant' => ['price' => 1999, 'weight_grams' => 250, ...$variant],
        ...$overrides,
    ];
}

it('adds a draft product with its first variant priced in minor units of the store\'s base currency', function (): void {
    $response = productRequest('POST', data: newProduct([
        'description' => ['en' => 'Breathable.'],
        'brand' => $this->brand->public_id,
        'categories' => [$this->shirts->public_id, $this->linen->public_id],
    ], ['compare_at_price' => 2500, 'sku' => 'LIN-001', 'barcode' => '4006381333931', 'dimensions' => ['length_mm' => 300, 'width_mm' => 200, 'height_mm' => 30]]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.slug', 'linen-shirt')
        ->assertJsonPath('data.brand_id', $this->brand->public_id)
        ->assertJsonPath('data.category_ids', [$this->shirts->public_id, $this->linen->public_id])
        ->assertJsonPath('data.primary_category_id', $this->shirts->public_id)
        ->assertJsonPath('data.variants.0.price.amount', 1999)
        ->assertJsonPath('data.variants.0.price.currency', 'NGN')
        ->assertJsonPath('data.variants.0.compare_at_price.amount', 2500)
        ->assertJsonPath('data.variants.0.sku', 'LIN-001')
        ->assertJsonPath('data.variants.0.requires_shipping', true)
        ->assertJsonPath('data.variants.0.weight_grams', 250)
        ->assertJsonPath('data.variants.0.dimensions', ['length_mm' => 300, 'width_mm' => 200, 'height_mm' => 30]);

    expect($response->json('data.id'))->toHaveLength(26)
        ->and($response->json('data.variants.0.id'))->toHaveLength(26)
        ->and($this->store->run(static fn (): array => Audit::query()->where('event', 'created')->whereIn('auditable_type', ['product', 'product_variant'])->pluck('auditable_type')->sort()->values()->all()))
        ->toBe(['product', 'product_variant']);
});

it('adds a variant without a price, which leaves the base currency free until it is priced', function (): void {
    $settings = fn (array $data): TestResponse => test()->withToken($this->store->run(fn (): string => app(AccessTokenIssuer::class)->issue($this->owner, StaffMember::GUARD, 'test')->plainTextToken))
        ->patchJson(storeUrl('product-store', '/api/v1/staff/store/settings'), $data);

    productRequest('POST', data: newProduct(variant: ['price' => null]))->assertUnprocessable()->assertJsonValidationErrors('variant.price');
    productRequest('POST', data: newProduct(variant: ['compare_at_price' => 2500, 'price' => null]))->assertUnprocessable();

    $unpriced = newProduct(['name' => ['en' => 'Unpriced']]);
    unset($unpriced['variant']['price']);

    $response = productRequest('POST', data: $unpriced)->assertCreated()->assertJsonPath('data.variants.0.price', null);
    [$productId, $variantId] = [$response->json('data.id'), $response->json('data.variants.0.id')];

    $settings(['currency' => 'USD'])->assertOk();

    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['compare_at_price' => 2500])->assertUnprocessable()->assertJsonValidationErrors('compare_at_price');
    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['price' => 1500])->assertOk()->assertJsonPath('data.price.amount', 1500)->assertJsonPath('data.price.currency', 'USD');
    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['price' => null])->assertUnprocessable()->assertJsonValidationErrors('price');

    $settings(['currency' => 'NGN'])->assertConflict()->assertJsonPath('code', 'pricing_settings_locked');
});

it('keeps prices, currencies and compare-at prices together in the database', function (): void {
    $id = productRequest('POST', data: newProduct(variant: ['compare_at_price' => 2500]))->assertCreated()->json('data.id');

    $this->store->run(static function () use ($id): void {
        $variants = static fn () => DB::table('product_variants')->whereIn('product_id', Product::query()->where('public_id', $id)->select('id'));

        expect(static fn () => $variants()->update(['price_amount' => null]))->toThrow(QueryException::class, 'product_variants_compare_at_above_price')
            ->and(static fn () => $variants()->update(['price_amount' => null, 'compare_at_price_amount' => null]))->toThrow(QueryException::class, 'product_variants_priced_have_currency')
            ->and(static fn () => $variants()->update(['currency' => null]))->toThrow(QueryException::class, 'product_variants_priced_have_currency')
            ->and(static fn () => $variants()->update(['price_amount' => -1]))->toThrow(QueryException::class, 'product_variants_price_not_negative')
            ->and($variants()->update(['price_amount' => null, 'compare_at_price_amount' => null, 'currency' => null]))->toBe(1)
            ->and(static fn () => $variants()->update(['compare_at_price_amount' => 100]))->toThrow(QueryException::class, 'product_variants_compare_at_above_price')
            ->and(static fn () => $variants()->update(['currency' => 'NGN']))->toThrow(QueryException::class, 'product_variants_priced_have_currency');
    });
});

it('takes amounts only as whole minor units, never decimals or strings', function (mixed $price): void {
    productRequest('POST', data: newProduct(variant: ['price' => $price]))->assertUnprocessable()->assertJsonValidationErrors('variant.price');
})->with(['a decimal' => 19.99, 'a decimal string' => '19.99', 'a whole number as a string' => '1999', 'below zero' => -1]);

it('keeps the compare-at price above the price, when adding, when changing either one, and in the database', function (): void {
    productRequest('POST', data: newProduct(variant: ['compare_at_price' => 1999]))->assertUnprocessable()->assertJsonValidationErrors('variant.compare_at_price');

    $response = productRequest('POST', data: newProduct(variant: ['compare_at_price' => 2500]))->assertCreated();
    [$productId, $variantId] = [$response->json('data.id'), $response->json('data.variants.0.id')];

    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['price' => 2500])->assertUnprocessable()->assertJsonValidationErrors('compare_at_price');
    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['compare_at_price' => 1500])->assertUnprocessable()->assertJsonValidationErrors('compare_at_price');
    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['price' => 2400, 'compare_at_price' => null])->assertOk()->assertJsonPath('data.compare_at_price', null);

    $this->store->run(static function (): void {
        expect(static fn () => DB::table('product_variants')->update(['compare_at_price_amount' => 100]))->toThrow(QueryException::class);
    });
});

it('needs a weight for anything that ships, and dimensions all or none', function (): void {
    productRequest('POST', data: newProduct(variant: ['weight_grams' => null]))->assertUnprocessable()->assertJsonValidationErrors('variant.weight_grams');
    productRequest('POST', data: newProduct(variant: ['dimensions' => ['length_mm' => 300]]))->assertUnprocessable()->assertJsonValidationErrors(['variant.dimensions.width_mm', 'variant.dimensions.height_mm']);

    $response = productRequest('POST', data: newProduct(variant: ['weight_grams' => null, 'requires_shipping' => false]))->assertCreated();
    [$productId, $variantId] = [$response->json('data.id'), $response->json('data.variants.0.id')];

    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['requires_shipping' => true])->assertUnprocessable()->assertJsonValidationErrors('weight_grams');
    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['requires_shipping' => true, 'weight_grams' => 900])->assertOk()->assertJsonPath('data.weight_grams', 900);

    $this->store->run(static function (): void {
        expect(static fn () => DB::table('product_variants')->update(['weight_grams' => null]))->toThrow(QueryException::class)
            ->and(static fn () => DB::table('product_variants')->update(['length_mm' => 10]))->toThrow(QueryException::class);
    });
});

it('keeps SKUs unique among variants outside the trash', function (): void {
    productRequest('POST', data: newProduct(variant: ['sku' => 'LIN-001']))->assertCreated();
    productRequest('POST', data: newProduct(['name' => ['en' => 'Other']], ['sku' => 'LIN-001']))->assertUnprocessable()->assertJsonValidationErrors('variant.sku');

    $this->store->run(static fn () => ProductVariant::query()->where('sku', 'LIN-001')->sole()->delete());

    productRequest('POST', data: newProduct(['name' => ['en' => 'Other']], ['sku' => 'LIN-001']))->assertCreated();

    $this->store->run(static function (): void {
        expect(static fn () => ProductVariant::factory()->create(['sku' => 'LIN-001']))->toThrow(QueryException::class);
    });
});

it('keeps the primary category among the product\'s categories', function (): void {
    productRequest('POST', data: newProduct(['categories' => [$this->shirts->public_id], 'primary_category' => $this->linen->public_id]))
        ->assertUnprocessable()->assertJsonValidationErrors('primary_category');

    $id = productRequest('POST', data: newProduct(['categories' => [$this->shirts->public_id, $this->linen->public_id], 'primary_category' => $this->linen->public_id]))
        ->assertCreated()->assertJsonPath('data.primary_category_id', $this->linen->public_id)->json('data.id');

    productRequest('PATCH', "/{$id}", ['categories' => [$this->linen->public_id]])->assertOk()->assertJsonPath('data.primary_category_id', $this->linen->public_id);
    productRequest('PATCH', "/{$id}", ['categories' => [$this->shirts->public_id]])->assertOk()->assertJsonPath('data.primary_category_id', $this->shirts->public_id);
    productRequest('PATCH', "/{$id}", ['primary_category' => $this->linen->public_id])->assertUnprocessable()->assertJsonValidationErrors('primary_category');
    productRequest('PATCH', "/{$id}", ['categories' => []])->assertOk()->assertJsonPath('data.category_ids', [])->assertJsonPath('data.primary_category_id', null);

    $this->store->run(function (): void {
        expect(fn () => DB::table('products')->update(['primary_category_id' => $this->linen->id]))->toThrow(QueryException::class);
    });
});

it('only uses brands and categories outside the trash, and keeps a product\'s brand when the brand is trashed later', function (): void {
    $id = productRequest('POST', data: newProduct(['brand' => $this->brand->public_id]))->assertCreated()->json('data.id');

    $this->store->run(fn () => Brand::query()->whereKey($this->brand->id)->sole()->delete());

    productRequest('GET', "/{$id}")->assertOk()->assertJsonPath('data.brand_id', $this->brand->public_id);
    productRequest('POST', data: newProduct(['name' => ['en' => 'Other'], 'brand' => $this->brand->public_id]))->assertUnprocessable()->assertJsonValidationErrors('brand');

    $this->store->run(fn () => Category::query()->whereKey($this->linen->id)->sole()->delete());
    productRequest('PATCH', "/{$id}", ['categories' => [$this->linen->public_id]])->assertUnprocessable()->assertJsonValidationErrors('categories.0');
});

it('changes only what is sent, keeps the slug when renamed, and removes the brand when it is null', function (): void {
    $id = productRequest('POST', data: newProduct(['name' => ['en' => 'Linen shirt'], 'brand' => $this->brand->public_id]))->json('data.id');

    productRequest('PATCH', "/{$id}", ['name' => ['en' => 'Linen shirt, relaxed fit'], 'brand' => null])
        ->assertOk()
        ->assertJsonPath('data.name', ['en' => 'Linen shirt, relaxed fit'])
        ->assertJsonPath('data.slug', 'linen-shirt')
        ->assertJsonPath('data.brand_id', null)
        ->assertJsonPath('data.variants.0.price.amount', 1999);
});

it('refuses a product over the plan\'s limit, not counting products in the trash', function (): void {
    allowProducts($this->store->getTenantKey(), 1);

    $id = productRequest('POST', data: newProduct())->assertCreated()->json('data.id');
    productRequest('POST', data: newProduct(['name' => ['en' => 'Second']]))->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');

    $this->store->run(static fn () => Product::query()->where('public_id', $id)->sole()->delete());

    productRequest('POST', data: newProduct(['name' => ['en' => 'Second']]))->assertCreated();
});

it('waits for a concurrent request adding a product, so two can\'t both take the last place', function (): void {
    allowProducts($this->store->getTenantKey(), 1);

    $this->store->run(static function (): void {
        $createProduct = static fn (): Product => app(CreateProduct::class)->handle(
            new ProductData(name: ['en' => 'Second']),
            new ProductVariantData(priceAmount: 1000, weightGrams: 100),
        );

        // The other request has checked the limit and added the store's last product, but not yet committed.
        $otherRequest = SecondConnection::open()->holdAdvisoryLock(ProductLimit::LIMIT_KEY);
        $otherRequest->run(static fn () => Product::factory()->connection(SecondConnection::NAME)->create(['name' => ['en' => 'First']]));

        expect($otherRequest->blocks($createProduct))->toBeTrue();

        $otherRequest->commit();

        expect($createProduct)->toThrow(UsageLimitReachedException::class)
            ->and(Product::query()->count())->toBe(1);
    });
});

it('prices variants in the store\'s base currency whatever currency the request names', function (): void {
    $response = productRequest('POST', data: newProduct(['currency' => 'USD'], ['currency' => 'EUR', 'price_currency' => 'GBP']))
        ->assertCreated()
        ->assertJsonPath('data.variants.0.price.currency', 'NGN');

    $productId = $response->json('data.id');
    $variantId = $response->json('data.variants.0.id');

    productRequest('PATCH', "/{$productId}/variants/{$variantId}", ['price' => 2500, 'currency' => 'USD'])
        ->assertOk()
        ->assertJsonPath('data.price.amount', 2500)
        ->assertJsonPath('data.price.currency', 'NGN');

    expect($this->store->run(static fn (): array => ProductVariant::query()->pluck('currency')->all()))->toBe(['NGN']);
});

it('locks the store\'s base currency once a product is priced', function (): void {
    $settings = fn (array $data): TestResponse => test()->withToken($this->store->run(fn (): string => app(AccessTokenIssuer::class)->issue($this->owner, StaffMember::GUARD, 'test')->plainTextToken))
        ->patchJson(storeUrl('product-store', '/api/v1/staff/store/settings'), $data);

    $settings(['currency' => 'USD'])->assertOk();
    tenancy()->end();
    $settings(['currency' => 'NGN'])->assertOk();
    tenancy()->end();

    productRequest('POST', data: newProduct())->assertCreated()->assertJsonPath('data.variants.0.price.currency', 'NGN');

    $settings(['currency' => 'USD'])->assertConflict()->assertJsonPath('code', 'pricing_settings_locked');
});

it('keeps the base currency locked while a priced variant is only in the trash', function (): void {
    productRequest('POST', data: newProduct())->assertCreated();

    $this->store->run(static function (): void {
        ProductVariant::query()->sole()->delete();
        Product::query()->sole()->delete();
    });

    expect($this->store->run(static fn (): bool => app(App\Shared\Money\PricedRecordsRegistry::class)->anyExist()))->toBeTrue();
});

it('changes a variant only through its own product', function (): void {
    $first = productRequest('POST', data: newProduct())->json('data');
    $second = productRequest('POST', data: newProduct(['name' => ['en' => 'Second']]))->json('data');

    productRequest('PATCH', "/{$second['id']}/variants/{$first['variants'][0]['id']}", ['price' => 500])->assertNotFound();

    expect($this->store->run(static fn (): Money => ProductVariant::query()->where('public_id', $first['variants'][0]['id'])->sole()->price)->minorAmount())->toBe(1999);
});

it('lists products newest first, by status, brand, category or the trash, a page at a time', function (): void {
    productRequest('POST', data: newProduct(['brand' => $this->brand->public_id]))->assertCreated();
    $second = productRequest('POST', data: newProduct(['name' => ['en' => 'Second'], 'categories' => [$this->linen->public_id]]))->json('data.id');

    expect(array_column(productRequest('GET')->json('data'), 'id')[0])->toBe($second)
        ->and(productRequest('GET', '?status=draft')->json('data'))->toHaveCount(2)
        ->and(productRequest('GET', '?status=active')->json('data'))->toHaveCount(0)
        ->and(productRequest('GET', '?brand='.$this->brand->public_id)->json('data'))->toHaveCount(1)
        ->and(productRequest('GET', '?category='.$this->linen->public_id)->json('data.0.id'))->toBe($second)
        ->and(productRequest('GET', '?per_page=1')->json('meta.next_cursor'))->toBeString()
        ->and(productRequest('GET', '?trashed=1')->json('data'))->toHaveCount(0);
});

it('lets catalog.view look but not change', function (): void {
    $product = productRequest('POST', data: newProduct())->json('data');

    productRequest('GET', as: $this->viewer)->assertOk();
    productRequest('GET', "/{$product['id']}", as: $this->viewer)->assertOk();
    productRequest('POST', data: newProduct(['name' => ['en' => 'Other']]), as: $this->viewer)->assertForbidden();
    productRequest('PATCH', "/{$product['id']}", ['name' => ['en' => 'Other']], as: $this->viewer)->assertForbidden();
    productRequest('PATCH', "/{$product['id']}/variants/{$product['variants'][0]['id']}", ['price' => 1], as: $this->viewer)->assertForbidden();
});

it('never shows or changes another store\'s products', function (): void {
    $this->otherStore = createStore('other-product-store');
    allowProducts($this->otherStore->getTenantKey(), 100);
    $otherOwner = $this->otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    $product = productRequest('POST', data: newProduct(['brand' => $this->brand->public_id]))->json('data');

    productRequest('GET', as: $otherOwner, subdomain: 'other-product-store')->assertOk()->assertJsonCount(0, 'data');
    productRequest('GET', "/{$product['id']}", as: $otherOwner, subdomain: 'other-product-store')->assertNotFound();
    productRequest('PATCH', "/{$product['id']}/variants/{$product['variants'][0]['id']}", ['price' => 1], as: $otherOwner, subdomain: 'other-product-store')->assertNotFound();
    productRequest('POST', data: newProduct(['brand' => $this->brand->public_id]), as: $otherOwner, subdomain: 'other-product-store')
        ->assertUnprocessable()->assertJsonValidationErrors('brand');
});
