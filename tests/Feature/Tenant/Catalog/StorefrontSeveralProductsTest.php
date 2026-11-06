<?php

declare(strict_types=1);

use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 13.1: comparing products and the shopper's recently viewed list are
 * kept in the browser; the API only fetches several products by ID and
 * stores nothing. What customers can't buy is left out, the order asked for
 * is kept, no more than catalog.max_products_fetched IDs are taken, and no
 * stock quantity is ever shown (section 14). Nothing crosses stores.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shop-front');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => 'en', 'enabled_locales' => ['en', 'fr']]));
});

afterEach(function (): void {
    deleteAllStores();
});

it('fetches the products asked for in the order asked for, leaving out what customers can\'t buy', function (): void {
    $ids = $this->store->run(static function (): array {
        $shirt = storefrontProduct(['en' => 'Linen shirt'], attributes: ['slug' => 'linen-shirt']);
        $socks = storefrontProduct(['en' => 'Wool socks'], attributes: ['slug' => 'wool-socks']);
        $draft = storefrontProduct(['en' => 'Draft hat'], ProductStatus::Draft, attributes: ['slug' => 'draft-hat']);
        $unpriced = storefrontProduct(['en' => 'Unpriced hat'], prices: [null], attributes: ['slug' => 'unpriced-hat']);
        $trashed = storefrontProduct(['en' => 'Trashed hat'], attributes: ['slug' => 'trashed-hat']);
        $trashed->delete();
        $belt = storefrontProduct(['en' => 'Belt'], attributes: ['slug' => 'belt']);

        // Neither oldest nor newest first: only the order asked for gives this.
        return [$socks->public_id, $draft->public_id, (string) Str::ulid(), $shirt->public_id, $unpriced->public_id, $belt->public_id, $trashed->public_id, $socks->public_id];
    });

    storefrontRequest('/several-products?'.http_build_query(['ids' => $ids]), 'fr')
        ->assertOk()
        ->assertJsonPath('data.*.slug', ['wool-socks', 'linen-shirt', 'belt'])
        ->assertJsonPath('data.0.variants.0.price.amount', 1999)
        ->assertJsonMissingPath('meta');
});

it('takes at most catalog.max_products_fetched IDs, and only IDs', function (): void {
    config(['catalog.max_products_fetched' => 2]);
    $ids = [(string) Str::ulid(), (string) Str::ulid(), (string) Str::ulid()];

    storefrontRequest('/several-products?'.http_build_query(['ids' => $ids]))->assertUnprocessable()->assertJsonValidationErrors('ids');
    storefrontRequest('/several-products?'.http_build_query(['ids' => array_slice($ids, 0, 2)]))->assertOk()->assertJsonCount(0, 'data');
    storefrontRequest('/several-products?'.http_build_query(['ids' => ['linen-shirt']]))->assertUnprocessable()->assertJsonValidationErrors('ids.0');
    storefrontRequest('/several-products')->assertUnprocessable()->assertJsonValidationErrors('ids');
});

it('never shows how many units are left', function (): void {
    $id = $this->store->run(static function (): string {
        $product = storefrontProduct(['en' => 'Linen shirt']);
        stockOnHandOf($product->variants()->sole());

        return $product->public_id;
    });

    $response = storefrontRequest('/several-products?'.http_build_query(['ids' => [$id]]))->assertOk()->assertJsonPath('data.0.variants.0.available_for_sale', true);

    expectNoStockQuantity($response);
});

it('never fetches another store\'s products', function (): void {
    $other = createStore('other-front');
    $id = $other->run(static fn (): string => storefrontProduct(['en' => 'Their shirt'])->public_id);

    storefrontRequest('/several-products?'.http_build_query(['ids' => [$id]]))->assertOk()->assertJsonCount(0, 'data');
    storefrontRequest('/several-products?'.http_build_query(['ids' => [$id]]), subdomain: 'other-front')->assertOk()->assertJsonCount(1, 'data');
});
