<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: shoppers see the image banners in one place on the storefront,
 * and the announcements, shown now: switched on, started (the start
 * included) and not yet ended (the end not included), an image banner only
 * with its image. In their order and language, a few at most. One store
 * never sees another's.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore();
    useLanguages($this->store, 'en', ['en', 'fr']);
    $this->store->run(static fn () => DB::table('store_settings')->update(['timezone' => 'Africa/Lagos']));
    $this->travelTo(CarbonImmutable::parse('2026-12-01T12:00:00Z'));
});

afterEach(function (): void {
    deleteAllStores();
});

it('shows the image banners in a place that are shown now, in their order and the shopper\'s language', function (): void {
    $second = addStorefrontBanner(['title' => ['en' => 'Second', 'fr' => 'Deuxième'], 'sort_order' => 2, 'link_url' => '/sale', 'link_label' => ['en' => 'Shop']], withImage: true);
    // Starts this very moment (13:00 in Lagos is 12:00 UTC): shown.
    $first = addStorefrontBanner(['title' => ['en' => 'First'], 'sort_order' => 1, 'starts_at' => '2026-12-01T13:00', 'ends_at' => '2026-12-02T00:00:00Z'], withImage: true);
    addStorefrontBanner(['title' => ['en' => 'Switched off'], 'is_active' => false], withImage: true);
    addStorefrontBanner(['title' => ['en' => 'Later'], 'starts_at' => '2026-12-01T13:00:01'], withImage: true);
    // Ends this very moment: no longer shown.
    addStorefrontBanner(['title' => ['en' => 'Ended'], 'ends_at' => '2026-12-01T12:00:00Z'], withImage: true);
    addStorefrontBanner(['title' => ['en' => 'No image yet']]);
    addStorefrontBanner(['title' => ['en' => 'Elsewhere'], 'position' => 'catalog_top'], withImage: true);

    $response = shopperRequest('banners?position=home_hero', 'fr')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$first['id'], $second['id']])
        ->and($response->json('data.1'))->toMatchArray([
            'kind' => 'image', 'title' => 'Deuxième', 'body' => null, 'link_url' => '/sale', 'link_label' => 'Shop',
            'text_color' => null, 'background_color' => null, 'ends_at' => null,
        ])
        ->and($response->json('data.1.image.url'))->toBe($second['image']['url'])
        ->and($response->json('data.0.ends_at'))->toBe('2026-12-02T00:00:00+00:00');

    shopperRequest('banners?position=catalog_top')->assertOk()->assertJsonPath('data.0.title', 'Elsewhere');

    // Never more than config storefront.banners_per_position, the first in order.
    config(['storefront.banners_per_position' => 1]);
    shopperRequest('banners?position=home_hero')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first['id']);
    shopperRequest('banners')->assertUnprocessable()->assertJsonValidationErrors('position');
    shopperRequest('banners?position=sidebar')->assertUnprocessable()->assertJsonValidationErrors('position');
});

it('shows at most three announcements shown now, in their order, and never image banners among them', function (): void {
    foreach ([4, 3, 2, 1] as $sortOrder) {
        addStorefrontBanner(['kind' => 'announcement', 'title' => ['en' => "Message {$sortOrder}"], 'sort_order' => $sortOrder]);
    }
    addStorefrontBanner(['kind' => 'announcement', 'title' => ['en' => 'Over'], 'sort_order' => 0, 'ends_at' => '2026-12-01T11:00:00Z']);
    addStorefrontBanner(['title' => ['en' => 'An image'], 'sort_order' => 0], withImage: true);

    expect(array_column(shopperRequest('announcements')->assertOk()->json('data'), 'title'))->toBe(['Message 1', 'Message 2', 'Message 3']);
});

it('never shows one store\'s banners on another\'s storefront', function (): void {
    addStorefrontBanner(['kind' => 'announcement', 'title' => ['en' => 'First store']]);

    ['owner' => $this->owner] = storefrontStore('other-front');
    shopperRequest('announcements', subdomain: 'other-front')->assertOk()->assertJsonCount(0, 'data');
    shopperRequest('announcements')->assertOk()->assertJsonPath('data.0.title', 'First store');
});
