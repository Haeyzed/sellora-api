<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Storefront\Actions\ChangeStorefrontSettings;
use App\Tenant\Storefront\Data\StorefrontSettingsChanges;
use App\Tenant\Storefront\Exceptions\MissingStorefrontSettingsException;
use App\Tenant\Storefront\Models\StorefrontSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: the storefront settings, how the storefront presents itself to
 * search engines and social sites. Staff with storefront.view see them and
 * with storefront.manage change them; shoppers read them in their language.
 * Every store has the row from the moment it is set up (section 10).
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

it('starts every store with the defaults, which staff with storefront.manage change and those with storefront.view only see', function (): void {
    $viewer = storefrontStaffWith($this->store, ['storefront.view']);
    $nobody = storefrontStaffWith($this->store, []);

    storefrontStaffRequest('GET', 'settings', as: $viewer)->assertOk()
        ->assertJsonPath('data.seo_title_pattern', '{title} · {store}')
        ->assertJsonPath('data.seo_description', null)
        ->assertJsonPath('data.robots', 'index_follow')
        ->assertJsonPath('data.share_image', null);
    storefrontStaffRequest('GET', 'settings', as: $nobody)->assertForbidden();
    storefrontStaffRequest('GET', 'settings')->assertUnauthorized();
    storefrontStaffRequest('PATCH', 'settings', ['robots' => 'noindex_follow'], as: $viewer)->assertForbidden();

    storefrontStaffRequest('PATCH', 'settings', [
        'seo_title_pattern' => '{store} | {title}',
        'seo_description' => ['en' => 'Handmade fabrics', 'fr' => 'Tissus faits main'],
        'robots' => 'noindex_follow',
    ], as: $this->owner)->assertOk()
        ->assertJsonPath('data.seo_title_pattern', '{store} | {title}')
        ->assertJsonPath('data.seo_description', ['en' => 'Handmade fabrics', 'fr' => 'Tissus faits main'])
        ->assertJsonPath('data.robots', 'noindex_follow');

    // Only the languages sent change; null removes one, and null for the whole field removes it.
    storefrontStaffRequest('PATCH', 'settings', ['seo_description' => ['fr' => null]], as: $this->owner)->assertOk()->assertJsonPath('data.seo_description', ['en' => 'Handmade fabrics']);
    storefrontStaffRequest('PATCH', 'settings', ['seo_description' => null], as: $this->owner)->assertOk()->assertJsonPath('data.seo_description', null);

    expect($this->store->run(static fn (): int => Audit::query()->where('auditable_type', 'storefront_settings')->count()))->toBeGreaterThan(0);
});

it('refuses a title pattern without the page\'s title, a robots value it doesn\'t know and a language the store doesn\'t publish in', function (): void {
    storefrontStaffRequest('PATCH', 'settings', ['seo_title_pattern' => '{store}'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('seo_title_pattern');
    storefrontStaffRequest('PATCH', 'settings', ['robots' => 'everything'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('robots');
    storefrontStaffRequest('PATCH', 'settings', ['seo_description' => ['de' => 'Stoffe']], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('seo_description');

    // The database holds the same line for the title pattern.
    expect(fn () => $this->store->run(static fn () => DB::table('storefront_settings')->update(['seo_title_pattern' => '{store}'])))->toThrow(Illuminate\Database\QueryException::class);
});

it('keeps one share image on the public disk, served on the store\'s own domain, and removes it with its file', function (): void {
    $first = storefrontStaffRequest('POST', 'settings/share-image', ['image' => UploadedFile::fake()->image('share.png', 1200, 630)], as: $this->owner)->assertOk()->json('data.share_image');
    $second = storefrontStaffRequest('POST', 'settings/share-image', ['image' => UploadedFile::fake()->image('share-2.png', 1200, 630)], as: $this->owner)->assertOk()->json('data.share_image');

    expect($second['url'])->toStartWith(storeUrl('front-store', '/tenancy/assets/'))
        ->and($second['id'])->not->toBe($first['id'])
        ->and($this->store->run(static fn (): array => Media::query()->pluck('disk')->all()))->toBe(['public']);

    shopperRequest('seo')->assertOk()->assertJsonPath('data.share_image.id', $second['id']);

    storefrontStaffRequest('DELETE', 'settings/share-image', as: $this->owner)->assertNoContent();

    expect($this->store->run(static fn (): int => Media::query()->count()))->toBe(0);
    shopperRequest('seo')->assertOk()->assertJsonPath('data.share_image', null);
});

it('shows shoppers the settings in their language, with the store\'s name and the robots meta tag', function (): void {
    storefrontStaffRequest('PATCH', 'settings', ['seo_description' => ['en' => 'Handmade fabrics', 'fr' => 'Tissus faits main'], 'robots' => 'noindex_nofollow'], as: $this->owner)->assertOk();
    $storeName = $this->store->run(static fn (): string => app(App\Tenant\Settings\Actions\FindStoreSettings::class)->handle()->name);

    shopperRequest('seo', 'fr')->assertOk()->assertExactJson(['data' => [
        'store_name' => $storeName,
        'title_pattern' => '{title} · {store}',
        'description' => 'Tissus faits main',
        'robots' => 'noindex, nofollow',
        'share_image' => null,
    ]]);
    // A language the store doesn't publish in falls back to its default.
    shopperRequest('seo', 'de')->assertOk()->assertJsonPath('data.description', 'Handmade fabrics');
});

it('never shows one store\'s settings on another\'s storefront', function (): void {
    storefrontStaffRequest('PATCH', 'settings', ['seo_description' => ['en' => 'First store only']], as: $this->owner)->assertOk();
    ['owner' => $otherOwner] = storefrontStore('other-front');

    shopperRequest('seo', subdomain: 'other-front')->assertOk()->assertJsonPath('data.description', null);
    shopperRequest('seo')->assertOk()->assertJsonPath('data.description', 'First store only');

    // The other store's owner, with their own store's token, can't read or change this store's settings.
    $otherToken = staffTokenFor($otherOwner, 'other-front');
    test()->withToken($otherToken)->getJson(storeUrl('front-store', '/api/v1/staff/storefront/settings'))->assertUnauthorized();
    test()->withToken($otherToken)->patchJson(storeUrl('front-store', '/api/v1/staff/storefront/settings'), ['robots' => 'noindex_nofollow'])->assertUnauthorized();
    tenancy()->end();
    shopperRequest('seo')->assertOk()->assertJsonPath('data.robots', 'index, follow');
});

it('gives a store set up before the migration its row, and a new store its row when it is set up, keeping what staff changed', function (): void {
    $migration = require database_path('migrations/tenant/2026_11_08_100000_create_storefront_settings_table.php');

    $robots = $this->store->run(static function () use ($migration): array {
        $migration->down();
        $migration->up();
        $created = StorefrontSettings::current()->robots->value;

        // Setting the store up again inserts nothing and changes nothing.
        app(ChangeStorefrontSettings::class)->handle(new StorefrontSettingsChanges(robots: App\Tenant\Storefront\Enums\RobotsDirective::NoindexFollow));
        app(StoreSettingsSetup::class)->initialize();

        return [$created, StorefrontSettings::current()->robots->value, StorefrontSettings::query()->count()];
    });

    config(['storefront.robots' => 'noindex_nofollow']);
    $fresh = createStore('new-front', ['country_code' => 'NG'])->run(static function (): string {
        DB::table('storefront_settings')->delete();
        app(StoreSettingsSetup::class)->initialize();

        return StorefrontSettings::current()->robots->value;
    });

    expect($robots)->toBe(['index_follow', 'noindex_follow', 1])
        ->and($fresh)->toBe('noindex_nofollow');
});

it('fails loudly when the row is missing, never filling in defaults', function (): void {
    $this->store->run(static function (): void {
        DB::table('storefront_settings')->delete();

        expect(fn () => StorefrontSettings::current())->toThrow(MissingStorefrontSettingsException::class)
            ->and(fn () => app(ChangeStorefrontSettings::class)->handle(new StorefrontSettingsChanges(seoTitlePattern: '{title}')))->toThrow(MissingStorefrontSettingsException::class)
            ->and(StorefrontSettings::query()->count())->toBe(0);
    });

    storefrontStaffRequest('GET', 'settings', as: $this->owner)->assertServerError();
});
