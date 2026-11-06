<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: the storefront's sitemap, in four parts (pages, products,
 * categories, brands), lists only what shoppers may see, each with its slug
 * and when it last changed, paged by cursor. One store never lists
 * another's.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore();
});

afterEach(function (): void {
    deleteAllStores();
});

it('lists only what shoppers may see in each part of the sitemap', function (): void {
    addStorefrontPage(['title' => ['en' => 'About us']], publish: true);
    addStorefrontPage(['title' => ['en' => 'Draft']]);
    $this->store->run(static function (): void {
        storefrontProduct(['en' => 'Linen shirt'], attributes: ['slug' => 'linen-shirt']);
        storefrontProduct(['en' => 'Draft shirt'], ProductStatus::Draft, attributes: ['slug' => 'draft-shirt']);
        storefrontProduct(['en' => 'Unpriced shirt'], prices: [null], attributes: ['slug' => 'unpriced-shirt']);
        Category::factory()->create(['name' => ['en' => 'Shirts']]);
        Category::factory()->create(['name' => ['en' => 'Old']])->delete();
        Brand::factory()->create(['name' => ['en' => 'Ada']]);
        Brand::factory()->create(['name' => ['en' => 'Gone']])->delete();
    });

    shopperRequest('sitemap/pages')->assertOk()->assertJsonPath('data.*.slug', ['about-us'])->assertJsonStructure(['data' => [['slug', 'updated_at']], 'meta' => ['next_cursor']]);
    shopperRequest('sitemap/products')->assertOk()->assertJsonPath('data.*.slug', ['linen-shirt']);
    shopperRequest('sitemap/categories')->assertOk()->assertJsonPath('data.*.slug', ['shirts']);
    shopperRequest('sitemap/brands')->assertOk()->assertJsonPath('data.*.slug', ['ada']);
    shopperRequest('sitemap/customers')->assertNotFound();
});

it('pages each part by cursor, and never lists another store\'s', function (): void {
    $this->store->run(static function (): void {
        foreach (['Ada', 'Bee', 'Cee'] as $name) {
            Brand::factory()->create(['name' => ['en' => $name]]);
        }
    });

    $first = shopperRequest('sitemap/brands?per_page=2')->assertOk()->assertJsonPath('data.*.slug', ['ada', 'bee']);
    $cursor = $first->json('meta.next_cursor');
    shopperRequest("sitemap/brands?per_page=2&cursor={$cursor}")->assertOk()->assertJsonPath('data.*.slug', ['cee']);

    storefrontStore('other-front');
    shopperRequest('sitemap/brands', subdomain: 'other-front')->assertOk()->assertJsonCount(0, 'data');
});
