<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: shoppers read the store's published pages and its homepage, in
 * their language, with no sign-in. Drafts and the trash are never shown. A
 * page's served body is cached per store, and every change to the content
 * is seen on the next visit; one store never sees another's pages.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    useRedisCache();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore();
    useLanguages($this->store, 'en', ['en', 'fr']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('shows a published page in the shopper\'s language, falling back to the store\'s default', function (): void {
    $page = addStorefrontPage([
        'title' => ['en' => 'About us', 'fr' => 'À propos'],
        'body' => ['en' => '<p>We weave.</p>', 'fr' => '<p>Nous tissons.</p>'],
        'seo_title' => ['en' => 'About the weavers'],
    ], publish: true);

    shopperRequest('pages/about-us', 'fr')->assertOk()->assertExactJson(['data' => [
        'id' => $page['id'],
        'slug' => 'about-us',
        'title' => 'À propos',
        'body' => '<p>Nous tissons.</p>',
        'seo' => ['title' => 'About the weavers', 'description' => null],
        'is_homepage' => false,
        'published_at' => $page['published_at'],
        'updated_at' => $page['updated_at'],
    ]]);
    shopperRequest('pages/about-us', 'de')->assertOk()->assertJsonPath('data.title', 'About us')->assertJsonPath('data.body', '<p>We weave.</p>');
});

it('never shows a draft, a page in the trash, or a homepage that isn\'t published', function (): void {
    $draft = addStorefrontPage(['title' => ['en' => 'Draft']]);
    storefrontStaffRequest('POST', "pages/{$draft['id']}/homepage", as: $this->owner)->assertOk();
    $trashed = addStorefrontPage(['title' => ['en' => 'Gone']], publish: true);
    storefrontStaffRequest('DELETE', "pages/{$trashed['id']}", as: $this->owner)->assertNoContent();

    shopperRequest('pages/draft')->assertNotFound();
    shopperRequest('pages/gone')->assertNotFound();
    shopperRequest('home')->assertNotFound();

    storefrontStaffRequest('POST', "pages/{$draft['id']}/publish", as: $this->owner)->assertOk();
    shopperRequest('home')->assertOk()->assertJsonPath('data.slug', 'draft')->assertJsonPath('data.is_homepage', true);
});

it('serves a page\'s body from the store\'s cache, and every change through the API is seen on the next visit', function (): void {
    // The clock stands still, so the change below lands in the same second as the cached read and only the renewed content version can show it.
    $this->freezeSecond();
    $page = addStorefrontPage(['body' => ['en' => '<p>First</p>']], publish: true);
    shopperRequest('pages/about-us')->assertOk()->assertJsonPath('data.body', '<p>First</p>');

    // Changed behind the cache's back, without touching the page's update time: shoppers still get the cached body.
    $this->store->run(static fn () => DB::table('pages')->where('public_id', $page['id'])->update(['body' => json_encode(['en' => '<p>Behind</p>'])]));
    shopperRequest('pages/about-us')->assertOk()->assertJsonPath('data.body', '<p>First</p>');

    storefrontStaffRequest('PATCH', "pages/{$page['id']}", ['body' => ['en' => '<p>Second</p>']], as: $this->owner)->assertOk();
    shopperRequest('pages/about-us')->assertOk()->assertJsonPath('data.body', '<p>Second</p>');
});

it('never shows one store\'s pages, or their cached bodies, on another\'s storefront', function (): void {
    addStorefrontPage(['body' => ['en' => '<p>First store</p>']], publish: true);
    shopperRequest('pages/about-us')->assertOk()->assertJsonPath('data.body', '<p>First store</p>');
    $first = $this->store;

    ['owner' => $this->owner] = storefrontStore('other-front');
    addStorefrontPage(['body' => ['en' => '<p>Other store</p>']], publish: true, subdomain: 'other-front');

    shopperRequest('pages/about-us', subdomain: 'other-front')->assertOk()->assertJsonPath('data.body', '<p>Other store</p>');
    shopperRequest('pages/about-us')->assertOk()->assertJsonPath('data.body', '<p>First store</p>');

    // A page only one store has is not found on the other.
    $first->run(static fn () => DB::table('pages')->update(['slug' => 'first-only']));
    shopperRequest('pages/first-only', subdomain: 'other-front')->assertNotFound();
});
