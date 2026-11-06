<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: the storefront's banners, for staff. Staff with storefront.view
 * see them all; with storefront.manage they add image banners (in a place
 * from config) and announcements, change, schedule and delete them, and set
 * an image banner's image. Times without an offset are read in the store's
 * timezone and kept in UTC.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore();
    useLanguages($this->store, 'en', ['en', 'fr']);
    $this->store->run(static fn () => DB::table('store_settings')->update(['timezone' => 'Africa/Lagos']));
});

afterEach(function (): void {
    deleteAllStores();
});

it('adds an image banner and an announcement, which staff with storefront.view only see', function (): void {
    $viewer = storefrontStaffWith($this->store, ['storefront.view']);

    $banner = addStorefrontBanner([
        'title' => ['en' => 'Summer sale', 'fr' => 'Soldes d\'été'],
        'body' => ['en' => 'Up to half off'],
        'link_url' => '/sale',
        'link_label' => ['en' => 'Shop now'],
        'text_color' => '#FFFFFF',
        'background_color' => '#1a1a1a',
        'sort_order' => 2,
    ]);
    $announcement = addStorefrontBanner(['kind' => 'announcement', 'title' => ['en' => 'Free delivery this week']]);

    expect($banner)->toMatchArray([
        'kind' => 'image', 'position' => 'home_hero', 'title' => ['en' => 'Summer sale', 'fr' => 'Soldes d\'été'], 'body' => ['en' => 'Up to half off'],
        'link_url' => '/sale', 'link_label' => ['en' => 'Shop now'], 'text_color' => '#ffffff', 'background_color' => '#1a1a1a',
        'starts_at' => null, 'ends_at' => null, 'is_active' => true, 'sort_order' => 2, 'image' => null,
    ])->and($announcement)->toMatchArray(['kind' => 'announcement', 'position' => null]);

    storefrontStaffRequest('GET', 'banners?kind=announcement', as: $viewer)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $announcement['id']);
    storefrontStaffRequest('GET', "banners/{$banner['id']}", as: $viewer)->assertOk()->assertJsonPath('data.title.en', 'Summer sale');
    storefrontStaffRequest('POST', 'banners', ['kind' => 'announcement', 'title' => ['en' => 'No']], as: $viewer)->assertForbidden();
    storefrontStaffRequest('PATCH', "banners/{$banner['id']}", ['is_active' => false], as: $viewer)->assertForbidden();
    storefrontStaffRequest('DELETE', "banners/{$banner['id']}", as: $viewer)->assertForbidden();
    storefrontStaffRequest('GET', 'banners')->assertUnauthorized();
});

it('reads times without an offset in the store\'s timezone, keeps them in UTC, and refuses an end before the start', function (): void {
    $banner = addStorefrontBanner(['starts_at' => '2026-12-01T09:00', 'ends_at' => '2026-12-08T09:00:00+00:00']);

    expect($banner['starts_at'])->toBe('2026-12-01T08:00:00+00:00')->and($banner['ends_at'])->toBe('2026-12-08T09:00:00+00:00');

    storefrontStaffRequest('PATCH', "banners/{$banner['id']}", ['ends_at' => '2026-12-01T09:00'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    storefrontStaffRequest('PATCH', "banners/{$banner['id']}", ['starts_at' => '2026-12-09T00:00'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    storefrontStaffRequest('PATCH', "banners/{$banner['id']}", ['starts_at' => null, 'ends_at' => null], as: $this->owner)->assertOk()->assertJsonPath('data.starts_at', null)->assertJsonPath('data.ends_at', null);
});

it('refuses a place for an announcement, an image banner without a known place, a changed kind, colours of another form and button text without a link', function (): void {
    $add = fn (array $data) => storefrontStaffRequest('POST', 'banners', ['title' => ['en' => 'Sale'], ...$data], as: $this->owner);

    $add(['kind' => 'announcement', 'position' => 'home_hero'])->assertUnprocessable()->assertJsonValidationErrors('position');
    $add(['kind' => 'image'])->assertUnprocessable()->assertJsonValidationErrors('position');
    $add(['kind' => 'image', 'position' => 'sidebar'])->assertUnprocessable()->assertJsonValidationErrors('position');
    $add(['kind' => 'image', 'position' => 'home_hero', 'title' => ['fr' => 'Soldes']])->assertUnprocessable()->assertJsonValidationErrors('title');
    foreach (['#fff', 'ffffff', 'white', '#gggggg'] as $color) {
        $add(['kind' => 'announcement', 'text_color' => $color])->assertUnprocessable()->assertJsonValidationErrors('text_color');
    }
    $add(['kind' => 'announcement', 'link_label' => ['en' => 'Go']])->assertUnprocessable()->assertJsonValidationErrors('link_label');
    $add(['kind' => 'announcement', 'link_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('link_url');

    $banner = addStorefrontBanner(['link_url' => '/sale', 'link_label' => ['en' => 'Shop now', 'fr' => 'Acheter'], 'body' => ['en' => 'Half off', 'fr' => 'Moitié prix']]);
    storefrontStaffRequest('PATCH', "banners/{$banner['id']}", ['kind' => 'announcement'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('kind');

    // Removing the link removes its button text too, and a text sent as null goes in every language.
    storefrontStaffRequest('PATCH', "banners/{$banner['id']}", ['link_url' => null, 'body' => null], as: $this->owner)->assertOk()
        ->assertJsonPath('data.link_url', null)->assertJsonPath('data.link_label', null)->assertJsonPath('data.body', null);
    expect($this->store->run(static fn (): array => (array) DB::table('banners')->where('public_id', $banner['id'])->first(['body', 'link_label'])))->toBe(['body' => null, 'link_label' => null]);
});

it('sets an image banner\'s image on the public disk, refuses one for an announcement, and deletes the file with the banner', function (): void {
    $banner = addStorefrontBanner(withImage: true);
    $announcement = addStorefrontBanner(['kind' => 'announcement']);

    expect($banner['image']['url'])->toStartWith(storeUrl('front-store', '/tenancy/assets/'))
        ->and($this->store->run(static fn (): array => Media::query()->pluck('disk')->all()))->toBe(['public']);

    storefrontStaffRequest('POST', "banners/{$announcement['id']}/image", ['image' => UploadedFile::fake()->image('a.jpg', 800, 200)], as: $this->owner)
        ->assertUnprocessable()->assertJsonPath('code', 'banner_has_no_image');

    storefrontStaffRequest('DELETE', "banners/{$banner['id']}/image", as: $this->owner)->assertNoContent();
    storefrontStaffRequest('GET', "banners/{$banner['id']}", as: $this->owner)->assertJsonPath('data.image', null);

    $again = addStorefrontBanner(withImage: true);
    storefrontStaffRequest('DELETE', "banners/{$again['id']}", as: $this->owner)->assertNoContent();
    expect($this->store->run(static fn (): int => Media::query()->count()))->toBe(0);
});

it('lets the database refuse a place on an announcement, a colour of another form and an end before the start', function (): void {
    $banner = addStorefrontBanner();
    $announcement = addStorefrontBanner(['kind' => 'announcement']);

    $this->store->run(static function () use ($banner, $announcement): void {
        $update = static fn (string $id, array $columns) => DB::table('banners')->where('public_id', $id)->update($columns);

        expect(fn () => $update($announcement['id'], ['position' => 'home_hero']))->toThrow(QueryException::class)
            ->and(fn () => $update($banner['id'], ['position' => null]))->toThrow(QueryException::class)
            ->and(fn () => $update($banner['id'], ['text_color' => '#FFFFFF']))->toThrow(QueryException::class)
            ->and(fn () => $update($banner['id'], ['link_label' => json_encode(['en' => 'Go']), 'link_url' => null]))->toThrow(QueryException::class)
            ->and(fn () => $update($banner['id'], ['starts_at' => '2026-12-02 00:00:00+00', 'ends_at' => '2026-12-01 00:00:00+00']))->toThrow(QueryException::class);
    });
});
