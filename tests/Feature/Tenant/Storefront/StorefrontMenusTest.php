<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: shoppers read a menu by its key, with no sign-in, in their
 * language. Only links they may follow are shown: to a published page, a
 * category or brand outside the trash, a product they can see, or an
 * address. A hidden link's own links go with it. One store never sees
 * another's menus.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore();
    useLanguages($this->store, 'en', ['en', 'fr']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('shows only the links shoppers may follow, in their language, with what the storefront needs to build each address', function (): void {
    $published = addStorefrontPage(['title' => ['en' => 'About us']], publish: true);
    $draft = addStorefrontPage(['title' => ['en' => 'Draft']]);
    $unpublished = addStorefrontPage(['title' => ['en' => 'Old news']], publish: true);
    storefrontStaffRequest('POST', "pages/{$unpublished['id']}/unpublish", as: $this->owner)->assertOk();
    $trashedPage = addStorefrontPage(['title' => ['en' => 'Gone']], publish: true);
    storefrontStaffRequest('DELETE', "pages/{$trashedPage['id']}", as: $this->owner)->assertNoContent();

    $catalog = $this->store->run(static fn (): array => [
        'category' => Category::factory()->create(['name' => ['en' => 'Shirts']]),
        'trashedCategory' => Category::factory()->create(['name' => ['en' => 'Hats']]),
        'brand' => Brand::factory()->create(['name' => ['en' => 'Weavers']]),
        'trashedBrand' => Brand::factory()->create(['name' => ['en' => 'Gone brand']]),
        'product' => storefrontProduct(['en' => 'Linen shirt']),
        'draftProduct' => storefrontProduct(['en' => 'Secret shirt'], ProductStatus::Draft),
        'unpricedProduct' => storefrontProduct(['en' => 'Unpriced shirt'], prices: [null]),
    ]);

    addStorefrontMenu('main', [
        menuLink(['en' => 'Shop', 'fr' => 'Boutique'], 'category', target: $catalog['category']->public_id, children: [
            menuLink('Linen shirt', 'product', target: $catalog['product']->public_id),
            menuLink('Secret', 'product', target: $catalog['draftProduct']->public_id),
            menuLink('Unpriced', 'product', target: $catalog['unpricedProduct']->public_id),
            menuLink('Hats', 'category', target: $catalog['trashedCategory']->public_id),
        ]),
        menuLink('Hats', 'category', target: $catalog['trashedCategory']->public_id, children: [menuLink('Under hats', 'url', url: '/hats-sale')]),
        menuLink('Weavers', 'brand', target: $catalog['brand']->public_id),
        menuLink('Gone brand', 'brand', target: $catalog['trashedBrand']->public_id),
        menuLink(['en' => 'About', 'fr' => 'À propos'], 'page', target: $published['id']),
        menuLink('Draft', 'page', target: $draft['id']),
        menuLink('Old news', 'page', target: $unpublished['id']),
        [...menuLink('Call us', 'url', url: 'tel:+2348012345678'), 'opens_in_new_tab' => true],
    ]);
    // Moved to the trash after the menu was saved, as merchants do.
    $this->store->run(static function () use ($catalog): void {
        $catalog['trashedCategory']->delete();
        $catalog['trashedBrand']->delete();
    });

    shopperRequest('menus/main', 'fr')->assertOk()->assertExactJson(['data' => [
        'key' => 'main',
        'items' => [
            ['label' => 'Boutique', 'type' => 'category', 'target' => $catalog['category']->public_id, 'slug' => $catalog['category']->slug, 'url' => null, 'opens_in_new_tab' => false, 'children' => [
                ['label' => 'Linen shirt', 'type' => 'product', 'target' => $catalog['product']->public_id, 'slug' => $catalog['product']->slug, 'url' => null, 'opens_in_new_tab' => false, 'children' => []],
            ]],
            ['label' => 'Weavers', 'type' => 'brand', 'target' => $catalog['brand']->public_id, 'slug' => $catalog['brand']->slug, 'url' => null, 'opens_in_new_tab' => false, 'children' => []],
            ['label' => 'À propos', 'type' => 'page', 'target' => $published['id'], 'slug' => 'about-us', 'url' => null, 'opens_in_new_tab' => false, 'children' => []],
            ['label' => 'Call us', 'type' => 'url', 'target' => null, 'slug' => null, 'url' => 'tel:+2348012345678', 'opens_in_new_tab' => true, 'children' => []],
        ],
    ]]);
});

it('answers not found for a key the store has no menu for, and never shows one store\'s menu on another\'s storefront', function (): void {
    addStorefrontMenu('main', [menuLink('First store', 'url', url: '/first')]);
    shopperRequest('menus/footer')->assertNotFound();

    ['owner' => $this->owner] = storefrontStore('other-front');
    shopperRequest('menus/main', subdomain: 'other-front')->assertNotFound();

    addStorefrontMenu('main', [menuLink('Other store', 'url', url: '/other')], subdomain: 'other-front');
    shopperRequest('menus/main', subdomain: 'other-front')->assertOk()->assertJsonPath('data.items.0.label', 'Other store');
    shopperRequest('menus/main')->assertOk()->assertJsonPath('data.items.0.label', 'First store');
});
