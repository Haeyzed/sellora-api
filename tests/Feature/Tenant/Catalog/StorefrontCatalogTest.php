<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Jobs\RefreshBrandProductsSearchText;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/*
 * Sections 3.3, 11 and 13: customers see only published products with at
 * least one priced variant, and only those variants; texts come in the
 * language they ask for, or the store's default. The read endpoints are open
 * and rate-limited per visitor. Search matches names in any language, SKUs,
 * barcodes and brand names, case-insensitively, and stays current when a
 * brand is renamed or a SKU or barcode changes. Nothing crosses stores.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shop-front');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => 'en', 'enabled_locales' => ['en', 'fr']]));
});

afterEach(function (): void {
    deleteAllStores();
});

function storefront(string $path, ?string $acceptLanguage = null, string $subdomain = 'shop-front'): TestResponse
{
    forgetSignIns();
    $response = test()->withHeaders(array_filter(['Accept-Language' => $acceptLanguage]))->getJson(storeUrl($subdomain, '/api/v1/catalog'.$path));
    tenancy()->end();

    return $response;
}

/**
 * Makes a product in the current store with one variant per given price (null for an unpriced variant).
 *
 * @param  array<string, string>  $name
 * @param  list<int|null>  $prices
 * @param  array<string, mixed>  $attributes
 */
function storefrontProduct(array $name, ProductStatus $status = ProductStatus::Active, array $prices = [1999], array $attributes = []): Product
{
    $product = Product::factory()->create(['name' => $name, ...$attributes]);
    $product->forceFill(['status' => $status])->save();

    foreach ($prices as $position => $price) {
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $price === null ? null : Money::ofMinor($price, 'NGN'),
            'currency' => $price === null ? null : 'NGN',
            'position' => $position,
            'attribute_signature' => "variant-{$position}",
        ]);
    }

    return $product;
}

it('shows customers only published products they can buy, and only their priced variants', function (): void {
    $this->store->run(static function (): void {
        storefrontProduct(['en' => 'Linen shirt'], prices: [1999, null], attributes: ['slug' => 'linen-shirt']);
        storefrontProduct(['en' => 'Draft shirt'], ProductStatus::Draft, attributes: ['slug' => 'draft-shirt']);
        storefrontProduct(['en' => 'Archived shirt'], ProductStatus::Archived, attributes: ['slug' => 'archived-shirt']);
        storefrontProduct(['en' => 'Unpriced shirt'], prices: [null], attributes: ['slug' => 'unpriced-shirt']);
        storefrontProduct(['en' => 'Trashed shirt'], attributes: ['slug' => 'trashed-shirt'])->delete();
    });

    storefront('/products')
        ->assertOk()
        ->assertJsonPath('data.*.slug', ['linen-shirt'])
        ->assertJsonCount(1, 'data.0.variants')
        ->assertJsonPath('data.0.variants.0.price.amount', 1999);

    storefront('/products/linen-shirt')->assertOk()->assertJsonPath('data.name', 'Linen shirt');

    foreach (['draft-shirt', 'archived-shirt', 'unpriced-shirt', 'trashed-shirt', 'nothing'] as $hidden) {
        storefront("/products/{$hidden}")->assertNotFound();
    }
});

it('answers in the language customers ask for, or the store\'s default', function (): void {
    $this->store->run(static function (): void {
        $brand = Brand::factory()->create(['name' => ['en' => 'Ada Fabrics', 'fr' => 'Tissus Ada']]);
        storefrontProduct(['en' => 'Linen shirt', 'fr' => 'Chemise en lin'], attributes: ['slug' => 'linen-shirt', 'brand_id' => $brand->id, 'description' => ['en' => 'Breathable.']]);
    });

    storefront('/products/linen-shirt', 'fr')->assertJsonPath('data.name', 'Chemise en lin')->assertJsonPath('data.brand.name', 'Tissus Ada')->assertJsonPath('data.description', 'Breathable.');
    storefront('/products/linen-shirt', 'fr-CA')->assertJsonPath('data.name', 'Chemise en lin');
    storefront('/products/linen-shirt', 'de')->assertJsonPath('data.name', 'Linen shirt')->assertHeader('Content-Language', 'en');
    storefront('/products/linen-shirt')->assertJsonPath('data.name', 'Linen shirt');
    storefront('/brands', 'fr')->assertJsonPath('data.0.name', 'Tissus Ada');
});

it('lists categories and brands outside the trash, with no sign-in needed', function (): void {
    $this->store->run(static function (): void {
        $shirts = Category::factory()->create(['name' => ['en' => 'Shirts']]);
        Category::factory()->create(['name' => ['en' => 'Linen'], 'parent_id' => $shirts->id]);
        Category::factory()->create(['name' => ['en' => 'Old']])->delete();
        Brand::factory()->create(['name' => ['en' => 'Ada Fabrics']]);
        Brand::factory()->create(['name' => ['en' => 'Gone']])->delete();
    });

    storefront('/categories')->assertOk()->assertJsonPath('data.*.name', ['Shirts', 'Linen'])->assertJsonPath('data.1.parent_id', storefront('/categories')->json('data.0.id'));
    storefront('/brands')->assertOk()->assertJsonPath('data.*.name', ['Ada Fabrics']);
});

it('finds products by name in any language, SKU, barcode or brand, ignoring case', function (): void {
    $this->store->run(static function (): void {
        $brand = Brand::factory()->create(['name' => ['en' => 'Ada Fabrics']]);
        $product = storefrontProduct(['en' => 'Linen shirt', 'fr' => 'Chemise en lin'], attributes: ['slug' => 'linen-shirt', 'brand_id' => $brand->id]);
        ProductVariant::query()->where('product_id', $product->id)->sole()->forceFill(['sku' => 'LIN-001', 'barcode' => '4006381333931'])->save();
        storefrontProduct(['en' => 'Wool scarf'], attributes: ['slug' => 'wool-scarf']);
    });

    foreach (['linen', 'CHEMISE', 'lin-001', '40063813', 'ada fab'] as $words) {
        storefront('/products?search='.urlencode($words))->assertOk()->assertJsonPath('data.*.slug', ['linen-shirt']);
    }

    storefront('/products?search=scarf')->assertJsonPath('data.*.slug', ['wool-scarf']);
    storefront('/products?search=nothing')->assertJsonPath('data', []);
});

it('keeps search current when a SKU or barcode changes or a brand is renamed', function (): void {
    $brand = $this->store->run(static function (): Brand {
        $brand = Brand::factory()->create(['name' => ['en' => 'Ada Fabrics']]);
        $product = storefrontProduct(['en' => 'Linen shirt'], prices: [1999, 2999], attributes: ['slug' => 'linen-shirt', 'brand_id' => $brand->id]);
        ProductVariant::query()->where('product_id', $product->id)->where('position', 0)->sole()->forceFill(['sku' => 'NEW-SKU', 'barcode' => '12345678'])->save();

        return $brand;
    });

    storefront('/products?search=new-sku')->assertJsonPath('data.*.slug', ['linen-shirt']);
    storefront('/products?search=12345678')->assertJsonPath('data.*.slug', ['linen-shirt']);

    $this->store->run(static fn () => Brand::query()->findOrFail($brand->id)->setTranslation('name', 'en', 'Bola Weaves')->save());
    storefront('/products?search=bola')->assertJsonPath('data.*.slug', ['linen-shirt']);
    storefront('/products?search=ada fab')->assertJsonPath('data', []);

    // A variant in the trash no longer finds its product, which customers still see through its other variant.
    $this->store->run(static fn () => ProductVariant::query()->where('sku', 'NEW-SKU')->sole()->delete());
    storefront('/products?search=new-sku')->assertJsonPath('data', []);
    storefront('/products?search=linen')->assertJsonPath('data.*.slug', ['linen-shirt']);
});

it('rebuilds search for a renamed brand with many products on the bulk queue', function (): void {
    $brand = $this->store->run(static function (): Brand {
        $brand = Brand::factory()->create(['name' => ['en' => 'Ada Fabrics']]);
        Product::factory()->count(51)->create(['brand_id' => $brand->id]);

        return $brand;
    });

    Queue::fake();
    $this->store->run(static fn () => $brand->setTranslation('name', 'en', 'Bola Weaves')->save());

    Queue::assertPushedOn('bulk', RefreshBrandProductsSearchText::class, static fn (RefreshBrandProductsSearchText $job): bool => $job->brandId === $brand->id);
    expect($this->store->run(static fn (): int => Product::query()->where('search_text', 'like', '%bola%')->count()))->toBe(0);

    $this->store->run(static fn () => app()->call([new RefreshBrandProductsSearchText($brand->id), 'handle']));
    expect($this->store->run(static fn (): int => Product::query()->where('search_text', 'like', '%bola weaves%')->count()))->toBe(51);
});

it('never shows or finds another store\'s products', function (): void {
    $other = createStore('other-front');
    $this->store->run(static fn () => storefrontProduct(['en' => 'Linen shirt'], attributes: ['slug' => 'linen-shirt']));
    $other->run(static fn () => storefrontProduct(['en' => 'Wool scarf'], attributes: ['slug' => 'wool-scarf']));

    storefront('/products', subdomain: 'other-front')->assertJsonPath('data.*.slug', ['wool-scarf']);
    storefront('/products?search=linen', subdomain: 'other-front')->assertJsonPath('data', []);
    storefront('/products/linen-shirt', subdomain: 'other-front')->assertNotFound();
});

it('limits how often one visitor may read the catalog', function (): void {
    config(['api.rate_limits.public' => 2]);

    storefront('/products')->assertOk();
    storefront('/brands')->assertOk();
    storefront('/categories')->assertTooManyRequests();
});

it('needs no sign-in, and a staff token changes nothing', function (): void {
    $token = $this->store->run(static function (): string {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        storefrontProduct(['en' => 'Draft shirt'], ProductStatus::Draft, attributes: ['slug' => 'draft-shirt']);

        return app(AccessTokenIssuer::class)->issue($owner, StaffMember::GUARD, 'test')->plainTextToken;
    });

    $this->withToken($token)->getJson(storeUrl('shop-front', '/api/v1/catalog/products/draft-shirt'))->assertNotFound();
    tenancy()->end();
});

it('needs every word to match, treats wildcards as plain text, and uses the trigram index', function (): void {
    $this->store->run(static function (): void {
        storefrontProduct(['en' => 'Linen shirt'], attributes: ['slug' => 'linen-shirt']);
        storefrontProduct(['en' => 'Wool scarf'], attributes: ['slug' => 'wool-scarf']);
    });

    storefront('/products?search='.urlencode('linen scarf'))->assertJsonPath('data', []);
    storefront('/products?search='.urlencode('wool scarf'))->assertJsonPath('data.*.slug', ['wool-scarf']);
    storefront('/products?search=__')->assertJsonPath('data', []);
    storefront('/products?search='.urlencode('%%'))->assertJsonPath('data', []);

    $index = $this->store->run(static fn (): ?string => DB::selectOne("select indexdef from pg_indexes where indexname = 'products_search_text_trigram'")?->indexdef);
    expect($index)->toContain('USING gin (search_text gin_trgm_ops)');
});

it('lets staff search every product, drafts included', function (): void {
    $token = $this->store->run(static function (): string {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        storefrontProduct(['en' => 'Wool scarf'], ProductStatus::Draft, attributes: ['slug' => 'wool-scarf']);
        storefrontProduct(['en' => 'Linen shirt'], attributes: ['slug' => 'linen-shirt']);

        return app(AccessTokenIssuer::class)->issue($owner, StaffMember::GUARD, 'test')->plainTextToken;
    });

    $this->withToken($token)->getJson(storeUrl('shop-front', '/api/v1/staff/catalog/products?search=wool'))->assertOk()->assertJsonPath('data.*.slug', ['wool-scarf']);
    tenancy()->end();
});
