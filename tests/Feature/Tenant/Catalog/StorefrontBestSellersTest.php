<?php

declare(strict_types=1);

use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Services\BestSellers;
use App\Tenant\Settings\Models\StoreSettings;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 13.1: best sellers are counted from paid, non-test orders: gross
 * units (before any return) over the last orders.best_sellers_period_days,
 * by when each order was placed. Unpaid, partly paid and cancelled orders
 * never count. Shoppers see only products they can buy now, at most
 * catalog.best_sellers.max_shown, with no sales figures or stock quantity.
 * The list is cached per store and category; availability never is.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shop-front');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => 'en', 'enabled_locales' => ['en']]));
});

afterEach(function (): void {
    deleteAllStores();
});

it('ranks products by units sold in paid, live orders over the last 30 days', function (): void {
    $this->store->run(static function (): void {
        ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $e, 'f' => $f, 'g' => $g, 'h' => $h] = bestSellerProducts(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);

        soldOrder([[$a, 5]]);
        soldOrder([[$b, 3], [$a, 1]], ['placed_at' => now()->subDays(29)]);
        soldOrder([[$b, 4]], ['payment_status' => 'unpaid', 'paid_at' => null]);
        // Refunded later still sold: units are counted gross.
        soldOrder([[$c, 2]], ['payment_status' => 'partially_refunded']);
        soldOrder([[$c, 3]], ['payment_status' => 'refunded']);
        soldOrder([[$d, 20]], ['is_test' => true]);
        soldOrder([[$e, 20]], ['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'customer']);
        soldOrder([[$f, 20]], ['payment_status' => 'partially_paid', 'paid_at' => null]);
        soldOrder([[$g, 20]], ['placed_at' => now()->subDays(31)]);
        // As many as b, but sold after it.
        soldOrder([[$h, 3]]);
    });

    $response = storefrontRequest('/best-sellers')->assertOk()->assertJsonPath('data.*.slug', ['a', 'c', 'b', 'h']);

    expectNoStockQuantity($response);
    expect(array_keys($response->json('data.0')))->not->toContain('units_sold', 'sold', 'sales');
});

it('shows only products that can be bought now, at most catalog.best_sellers.max_shown', function (): void {
    $this->store->run(static function (): void {
        ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d] = bestSellerProducts(['a', 'b', 'c', 'd']);
        soldOrder([[$a, 9], [$b, 8], [$c, 7], [$d, 6]]);
        soldOut($a->variants()->sole());
        $b->forceFill(['status' => ProductStatus::Draft])->save();
    });

    storefrontRequest('/best-sellers')->assertOk()->assertJsonPath('data.*.slug', ['c', 'd']);

    config(['catalog.best_sellers.max_shown' => 1]);
    storefrontRequest('/best-sellers')->assertOk()->assertJsonPath('data.*.slug', ['c']);
});

it('ranks within one category when asked, kept apart from the store-wide ranking', function (): void {
    // A shared cache, so a ranking kept for the whole store could be served for the category.
    useRedisCache();
    $category = $this->store->run(static function (): string {
        ['a' => $a, 'b' => $b, 'c' => $c] = bestSellerProducts(['a', 'b', 'c']);
        $shoes = Category::factory()->create(['name' => ['en' => 'Shoes']]);
        $b->categories()->attach($shoes);
        $c->categories()->attach($shoes);
        soldOrder([[$a, 9], [$b, 2], [$c, 5]]);

        return $shoes->public_id;
    });

    storefrontRequest('/best-sellers')->assertOk()->assertJsonPath('data.*.slug', ['a', 'c', 'b']);
    storefrontRequest('/best-sellers?category='.$category)->assertOk()->assertJsonPath('data.*.slug', ['c', 'b']);
    storefrontRequest('/best-sellers?category=01J00000000000000000000000')->assertUnprocessable()->assertJsonValidationErrors('category');
});

it('keeps the ranking between visits, but never what can be bought', function (): void {
    // The store's real cache, shared between requests as it is between a store's workers.
    useRedisCache();
    $this->store->run(static function (): void {
        ['a' => $a, 'b' => $b] = bestSellerProducts(['a', 'b']);
        soldOrder([[$a, 5], [$b, 3]]);
    });
    storefrontRequest('/best-sellers')->assertOk()->assertJsonPath('data.*.slug', ['a', 'b']);

    $this->store->run(static function (): void {
        soldOut(Product::query()->where('slug', 'a')->sole()->variants()->sole());
        ['c' => $c] = bestSellerProducts(['c']);
        soldOrder([[$c, 50]]);
    });

    storefrontRequest('/best-sellers')->assertJsonPath('data.*.slug', ['b']);
});

it('counts again after catalog.best_sellers.cache_minutes', function (): void {
    $this->store->run(function (): void {
        ['a' => $a, 'b' => $b] = bestSellerProducts(['a', 'b']);
        soldOrder([[$a, 5]]);
        $first = app(BestSellers::class)->candidateIds(null);
        soldOrder([[$b, 9]]);

        $this->travel(config()->integer('catalog.best_sellers.cache_minutes') - 1)->minutes();
        expect(app(BestSellers::class)->candidateIds(null))->toBe($first);

        $this->travel(2)->minutes();
        expect(app(BestSellers::class)->candidateIds(null))->toBe([$b->id, $a->id]);
    });
});

it('never counts another store\'s sales', function (): void {
    $other = createStore('other-front');
    $other->run(static function (): void {
        ['a' => $a] = bestSellerProducts(['a']);
        soldOrder([[$a, 5]]);
    });
    $this->store->run(static fn (): array => bestSellerProducts(['a']));

    storefrontRequest('/best-sellers')->assertOk()->assertJsonCount(0, 'data');
    storefrontRequest('/best-sellers', subdomain: 'other-front')->assertOk()->assertJsonPath('data.*.slug', ['a']);
});
