<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Storefront\Actions\MakeHomepage;
use App\Tenant\Storefront\Events\PagePublished;
use App\Tenant\Storefront\Events\PageUnpublished;
use App\Tenant\Storefront\Models\Page;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: the store's own pages, for staff. Staff with storefront.view
 * see them, drafts and the trash included; with storefront.manage they add,
 * change, publish, unpublish, trash and restore them, upload their images
 * and choose the homepage (one at a time, under a lock) and the pages that
 * serve the store's policies.
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

it('adds a page as a draft with its texts by language and a slug from its title, which staff with storefront.view only see', function (): void {
    $viewer = storefrontStaffWith($this->store, ['storefront.view']);

    $page = addStorefrontPage([
        'title' => ['en' => 'About us', 'fr' => 'À propos'],
        'body' => ['en' => '<p>We weave.</p>'],
        'seo_description' => ['en' => 'Who we are'],
    ]);

    expect($page)->toMatchArray([
        'title' => ['en' => 'About us', 'fr' => 'À propos'],
        'slug' => 'about-us',
        'body' => ['en' => '<p>We weave.</p>'],
        'seo_title' => null,
        'seo_description' => ['en' => 'Who we are'],
        'status' => 'draft',
        'is_homepage' => false,
        'images' => [],
        'published_at' => null,
    ]);

    storefrontStaffRequest('GET', 'pages', as: $viewer)->assertOk()->assertJsonPath('data.0.id', $page['id'])->assertJsonPath('meta.total', 1);
    storefrontStaffRequest('GET', "pages/{$page['id']}", as: $viewer)->assertOk()->assertJsonPath('data.slug', 'about-us');
    storefrontStaffRequest('POST', 'pages', ['title' => ['en' => 'Contact']], as: $viewer)->assertForbidden();
    storefrontStaffRequest('POST', "pages/{$page['id']}/publish", as: $viewer)->assertForbidden();
    storefrontStaffRequest('GET', 'pages', as: storefrontStaffWith($this->store, []))->assertForbidden();
    storefrontStaffRequest('GET', 'pages')->assertUnauthorized();
});

it('needs the title in the store\'s default language, and refuses a slug another page uses or a language the store doesn\'t publish in', function (): void {
    addStorefrontPage(['slug' => 'about']);

    storefrontStaffRequest('POST', 'pages', ['title' => ['fr' => 'À propos']], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('title');
    storefrontStaffRequest('POST', 'pages', ['title' => ['en' => 'About'], 'slug' => 'about'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('slug');
    storefrontStaffRequest('POST', 'pages', ['title' => ['en' => 'About'], 'slug' => 'Not A Slug'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('slug');
    storefrontStaffRequest('POST', 'pages', ['title' => ['en' => 'Kontakt', 'de' => 'Kontakt']], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('title');
});

it('changes only what is sent: the languages given, a field removed with null, and the slug only on purpose', function (): void {
    $page = addStorefrontPage(['title' => ['en' => 'About us', 'fr' => 'À propos'], 'body' => ['en' => '<p>We weave.</p>', 'fr' => '<p>Nous tissons.</p>'], 'seo_title' => ['en' => 'About']]);

    storefrontStaffRequest('PATCH', "pages/{$page['id']}", ['title' => ['en' => 'Our story', 'fr' => null], 'body' => null, 'seo_title' => null], as: $this->owner)->assertOk()
        ->assertJsonPath('data.title', ['en' => 'Our story'])
        ->assertJsonPath('data.body', null)
        ->assertJsonPath('data.seo_title', null)
        ->assertJsonPath('data.slug', 'about-us');

    // Gone in every language, not only the one the request was made in.
    expect($this->store->run(static fn (): array => (array) DB::table('pages')->where('public_id', $page['id'])->first(['body', 'seo_title'])))->toBe(['body' => null, 'seo_title' => null]);

    storefrontStaffRequest('PATCH', "pages/{$page['id']}", ['slug' => 'our-story'], as: $this->owner)->assertOk()->assertJsonPath('data.slug', 'our-story');
});

it('publishes once, keeping the first publication\'s time, unpublishes, and announces both', function (): void {
    Event::fake([PagePublished::class, PageUnpublished::class]);
    $page = addStorefrontPage();

    $first = storefrontStaffRequest('POST', "pages/{$page['id']}/publish", as: $this->owner)->assertOk()->assertJsonPath('data.status', 'published')->json('data.published_at');
    storefrontStaffRequest('POST', "pages/{$page['id']}/publish", as: $this->owner)->assertOk();
    storefrontStaffRequest('POST', "pages/{$page['id']}/unpublish", as: $this->owner)->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.published_at', $first);
    storefrontStaffRequest('POST', "pages/{$page['id']}/publish", as: $this->owner)->assertOk()->assertJsonPath('data.published_at', $first);

    Event::assertDispatchedTimes(PagePublished::class, 2);
    Event::assertDispatchedTimes(PageUnpublished::class, 1);
    Event::assertDispatched(PagePublished::class, static fn (PagePublished $event): bool => $event->pageId === $page['id']);

    // The database never holds a published page without its publication time.
    expect(fn () => $this->store->run(static fn () => DB::table('pages')->update(['published_at' => null])))->toThrow(QueryException::class);
});

it('moves a page to the trash, where it stops being the homepage, and restores it unless its slug was taken meanwhile', function (): void {
    Event::fake([PageUnpublished::class]);
    $page = addStorefrontPage(publish: true);
    storefrontStaffRequest('POST', "pages/{$page['id']}/homepage", as: $this->owner)->assertOk()->assertJsonPath('data.is_homepage', true);

    storefrontStaffRequest('DELETE', "pages/{$page['id']}", as: $this->owner)->assertNoContent();

    Event::assertDispatched(PageUnpublished::class);
    storefrontStaffRequest('GET', 'pages', as: $this->owner)->assertOk()->assertJsonPath('meta.total', 0);
    storefrontStaffRequest('GET', 'pages?trashed=1', as: $this->owner)->assertOk()->assertJsonPath('data.0.id', $page['id'])->assertJsonPath('data.0.is_homepage', false);
    storefrontStaffRequest('PATCH', "pages/{$page['id']}", ['title' => ['en' => 'Gone']], as: $this->owner)->assertNotFound();

    $taker = addStorefrontPage(['title' => ['en' => 'About us again'], 'slug' => 'about-us']);
    storefrontStaffRequest('POST', "pages/{$page['id']}/restore", as: $this->owner)->assertUnprocessable()->assertJsonPath('code', 'page_slug_taken');

    storefrontStaffRequest('PATCH', "pages/{$taker['id']}", ['slug' => 'about-us-2'], as: $this->owner)->assertOk();
    storefrontStaffRequest('POST', "pages/{$page['id']}/restore", as: $this->owner)->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.is_homepage', false);
});

it('keeps one homepage: choosing another takes it from the first, and the database refuses two', function (): void {
    $first = addStorefrontPage(['title' => ['en' => 'Home']]);
    $second = addStorefrontPage(['title' => ['en' => 'Summer']]);

    storefrontStaffRequest('POST', "pages/{$first['id']}/homepage", as: $this->owner)->assertOk();
    storefrontStaffRequest('POST', "pages/{$second['id']}/homepage", as: $this->owner)->assertOk();

    expect($this->store->run(static fn (): array => Page::query()->where('is_homepage', true)->pluck('slug')->all()))->toBe(['summer'])
        ->and(fn () => $this->store->run(static fn () => DB::table('pages')->update(['is_homepage' => true])))->toThrow(QueryException::class);
});

it('makes a page the homepage only once a change already under way has finished', function (): void {
    $page = addStorefrontPage();

    $this->store->run(static function () use ($page): void {
        $other = SecondConnection::open()->holdRowLock(StorefrontSettings::query()->findOrFail(1));

        expect($other->blocks(static fn () => app(MakeHomepage::class)->handle(Page::query()->where('public_id', $page['id'])->sole())))->toBeTrue();
        $other->rollBack();
    });
});

it('keeps a page\'s images on the public disk, served on the store\'s own domain, and removes one with its file', function (): void {
    $page = addStorefrontPage();

    $image = storefrontStaffRequest('POST', "pages/{$page['id']}/images", ['image' => UploadedFile::fake()->image('hero.jpg', 1600, 900)], as: $this->owner)->assertCreated()->json('data');

    expect($image['url'])->toStartWith(storeUrl('front-store', '/tenancy/assets/'))
        ->and($this->store->run(static fn (): array => Media::query()->pluck('disk')->all()))->toBe(['public']);
    storefrontStaffRequest('GET', "pages/{$page['id']}", as: $this->owner)->assertJsonPath('data.images.0.id', $image['id']);

    storefrontStaffRequest('DELETE', "pages/{$page['id']}/images/{$image['id']}", as: $this->owner)->assertNoContent();
    storefrontStaffRequest('DELETE', "pages/{$page['id']}/images/{$image['id']}", as: $this->owner)->assertNotFound();

    expect($this->store->run(static fn (): int => Media::query()->count()))->toBe(0);
});

it('chooses the pages serving the store\'s policies, which shoppers see only while published', function (): void {
    $privacy = addStorefrontPage(['title' => ['en' => 'Privacy policy', 'fr' => 'Confidentialité']], publish: true);
    $terms = addStorefrontPage(['title' => ['en' => 'Terms']]);

    storefrontStaffRequest('PATCH', 'settings', ['policy_pages' => ['privacy' => $privacy['id'], 'terms' => $terms['id']]], as: $this->owner)->assertOk()
        ->assertJsonPath('data.policy_pages.privacy', ['id' => $privacy['id'], 'slug' => 'privacy-policy', 'status' => 'published', 'trashed' => false])
        ->assertJsonPath('data.policy_pages.terms.status', 'draft')
        ->assertJsonPath('data.policy_pages.refunds', null);

    shopperRequest('seo', 'fr')->assertOk()
        ->assertJsonPath('data.policy_pages.privacy', ['slug' => 'privacy-policy', 'title' => 'Confidentialité'])
        ->assertJsonPath('data.policy_pages.terms', null);

    storefrontStaffRequest('DELETE', "pages/{$privacy['id']}", as: $this->owner)->assertNoContent();
    shopperRequest('seo')->assertOk()->assertJsonPath('data.policy_pages.privacy', null);
    storefrontStaffRequest('GET', 'settings', as: $this->owner)->assertOk()->assertJsonPath('data.policy_pages.privacy.trashed', true);

    // Only pages outside the trash, and only the four policies.
    storefrontStaffRequest('PATCH', 'settings', ['policy_pages' => ['privacy' => $privacy['id']]], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('policy_pages.privacy');
    storefrontStaffRequest('PATCH', 'settings', ['policy_pages' => ['cookies' => $terms['id']]], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('policy_pages');
    storefrontStaffRequest('PATCH', 'settings', ['policy_pages' => ['terms' => null]], as: $this->owner)->assertOk()->assertJsonPath('data.policy_pages.terms', null);
});
