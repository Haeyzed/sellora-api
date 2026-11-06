<?php

declare(strict_types=1);

use App\Tenant\Catalog\Actions\ReplaceProductLinks;
use App\Tenant\Catalog\Enums\ProductLinkType;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Services\SimilarProducts;
use App\Tenant\Settings\Models\StoreSettings;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 13.1: similar products are worked out when asked, never learnt:
 * the merchant's related links first, then the same category, then the same
 * brand, then a price within catalog.similar_products.price_band_percent.
 * Never the product itself, only what can be bought now, at most
 * catalog.similar_products.max_shown, and never a stock quantity. The
 * candidates' order is cached for a few minutes; availability never is.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shop-front');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => 'en', 'enabled_locales' => ['en']]));

    $this->store->run(static function (): void {
        $shoes = Category::factory()->create(['name' => ['en' => 'Shoes']]);
        $bags = Category::factory()->create(['name' => ['en' => 'Bags']]);
        $ada = Brand::factory()->create(['name' => ['en' => 'Ada']]);
        $other = Brand::factory()->create(['name' => ['en' => 'Other']]);

        $make = static function (string $slug, int $price, ?Category $category, ?Brand $brand, ProductStatus $status = ProductStatus::Active): Product {
            $product = storefrontProduct(['en' => $slug], $status, [$price], ['slug' => $slug, 'brand_id' => $brand?->id]);
            stockOnHandOf($product->variants()->sole());

            if ($category !== null) {
                $product->categories()->attach($category);
            }

            return $product;
        };

        $runner = $make('runner', 10000, $shoes, $ada);
        $picked = $make('picked-bag', 99000, $bags, $other);
        $make('older-shoe', 70000, $shoes, $other);
        $make('newer-shoe', 70000, $shoes, $other);
        $make('ada-bag', 80000, $bags, $ada);
        $make('similar-price-bag', 12500, $bags, $other);
        $make('pricey-bag', 12600, $bags, $other);
        $draft = $make('draft-shoe', 10000, $shoes, $ada, ProductStatus::Draft);
        $soldOut = $make('sold-out-shoe', 10000, $shoes, $ada);
        soldOut($soldOut->variants()->sole());
        $unpriced = $make('unpriced-shoe', 0, $shoes, $ada);
        $unpriced->variants()->sole()->forceFill(['price_amount' => null, 'currency' => null])->save();

        // The merchant may link to what shoppers can't buy now: it is left out until they can.
        app(ReplaceProductLinks::class)->handle($runner, ProductLinkType::Related, [$draft->public_id, $soldOut->public_id, $picked->public_id, $unpriced->public_id]);
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('suggests the merchant\'s related picks, then the same category, the same brand and a similar price, only what can be bought now', function (): void {
    $response = storefrontRequest('/products/runner/similar')
        ->assertOk()
        ->assertJsonPath('data.*.slug', ['picked-bag', 'newer-shoe', 'older-shoe', 'ada-bag', 'similar-price-bag']);

    expectNoStockQuantity($response);
    storefrontRequest('/products/draft-shoe/similar')->assertNotFound();
});

it('shows at most catalog.similar_products.max_shown', function (): void {
    config(['catalog.similar_products.max_shown' => 2]);

    storefrontRequest('/products/runner/similar')->assertOk()->assertJsonPath('data.*.slug', ['picked-bag', 'newer-shoe']);
});

it('keeps the list of candidates between visits, but never what can be bought', function (): void {
    // The store's real cache, shared between requests as it is between a store's workers.
    useRedisCache();
    storefrontRequest('/products/runner/similar')->assertOk()->assertJsonCount(5, 'data');

    $this->store->run(static function (): void {
        soldOut(Product::query()->where('slug', 'newer-shoe')->sole()->variants()->sole());
        $newest = storefrontProduct(['en' => 'Newest shoe'], attributes: ['slug' => 'newest-shoe']);
        stockOnHandOf($newest->variants()->sole());
        $newest->categories()->attach(Category::query()->where('name->en', 'Shoes')->sole());
    });

    // Sold out since: gone at once. Added since: not a candidate until the list is worked out again.
    storefrontRequest('/products/runner/similar')->assertJsonPath('data.*.slug', ['picked-bag', 'older-shoe', 'ada-bag', 'similar-price-bag']);
});

it('works the candidates out again after catalog.similar_products.cache_minutes', function (): void {
    $this->store->run(function (): void {
        $runner = Product::query()->where('slug', 'runner')->sole();
        $first = app(SimilarProducts::class)->candidateIdsFor($runner);

        $newest = storefrontProduct(['en' => 'Newest shoe'], attributes: ['slug' => 'newest-shoe']);
        $newest->categories()->attach(Category::query()->where('name->en', 'Shoes')->sole());

        $this->travel(config()->integer('catalog.similar_products.cache_minutes') - 1)->minutes();
        expect(app(SimilarProducts::class)->candidateIdsFor($runner))->toBe($first);

        $this->travel(2)->minutes();
        expect(app(SimilarProducts::class)->candidateIdsFor($runner))->toContain($newest->id)->not->toBe($first);
    });
});

it('never suggests another store\'s products', function (): void {
    $other = createStore('other-front');
    $other->run(static function (): void {
        $product = storefrontProduct(['en' => 'Their runner'], attributes: ['slug' => 'runner']);
        $product->categories()->attach(Category::factory()->create());
    });

    storefrontRequest('/products/runner/similar', subdomain: 'other-front')->assertOk()->assertJsonCount(0, 'data');
});
