<?php

declare(strict_types=1);

use App\Tenant\Catalog\Actions\ReplaceProductLinks;
use App\Tenant\Catalog\Enums\ProductLinkType;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductLink;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Database\QueryException;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 13.1: merchants pick the products shown beside a product, as
 * related products, upsells and cross-sells, in their order. Staff with
 * catalog.view see the links; with catalog.manage they replace one kind at
 * a time. A product never links to itself (the database refuses it too) or
 * to a product in the trash, and shoppers see only linked products they can
 * buy, never a stock quantity. Nothing crosses stores.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('shop-front');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['default_locale' => 'en', 'enabled_locales' => ['en']]));
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $this->products = $this->store->run(static fn (): array => [
        'shoes' => storefrontProduct(['en' => 'Running shoes'], attributes: ['slug' => 'running-shoes'])->public_id,
        'socks' => storefrontProduct(['en' => 'Wool socks'], attributes: ['slug' => 'wool-socks'])->public_id,
        'laces' => storefrontProduct(['en' => 'Laces'], attributes: ['slug' => 'laces'])->public_id,
        'draft' => storefrontProduct(['en' => 'Trail shoes'], ProductStatus::Draft, attributes: ['slug' => 'trail-shoes'])->public_id,
        'unpriced' => storefrontProduct(['en' => 'Insoles'], prices: [null], attributes: ['slug' => 'insoles'])->public_id,
    ]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('replaces one kind of a product\'s links at a time, in the order staff send', function (): void {
    ['shoes' => $shoes, 'socks' => $socks, 'laces' => $laces, 'draft' => $draft] = $this->products;

    productLinksRequest('PUT', "{$shoes}/links/cross_sell", ['product_ids' => [$laces, $socks, $draft]], $this->owner)
        ->assertOk()
        ->assertJsonPath('data.cross_sell.*.id', [$laces, $socks, $draft])
        ->assertJsonPath('data.cross_sell.2.status', 'draft')
        ->assertJsonPath('data.related', []);
    productLinksRequest('PUT', "{$shoes}/links/upsell", ['product_ids' => [$draft]], $this->owner)->assertOk();
    productLinksRequest('PUT', "{$shoes}/links/cross_sell", ['product_ids' => [$socks]], $this->owner)->assertOk();

    productLinksRequest('GET', "{$shoes}/links", as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.cross_sell.*.id', [$socks])
        ->assertJsonPath('data.upsell.*.id', [$draft])
        ->assertJsonPath('data.related', []);

    productLinksRequest('PUT', "{$shoes}/links/upsell", ['product_ids' => []], $this->owner)->assertOk()->assertJsonPath('data.upsell', []);
    productLinksRequest('PUT', "{$shoes}/links/bundle", ['product_ids' => []], $this->owner)->assertNotFound();
});

it('refuses a link to the product itself, to the trash, to nothing, twice, or one too many', function (): void {
    ['shoes' => $shoes, 'socks' => $socks, 'laces' => $laces] = $this->products;
    $this->store->run(static fn () => Product::query()->where('public_id', $laces)->sole()->delete());
    config(['catalog.max_links_per_type' => 1]);

    foreach ([[$shoes], [$laces], ['01J00000000000000000000000'], [$socks, $socks]] as $productIds) {
        productLinksRequest('PUT', "{$shoes}/links/related", ['product_ids' => $productIds], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('product_ids.0');
    }

    productLinksRequest('PUT', "{$shoes}/links/related", ['product_ids' => [$socks, $this->products['draft']]], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('product_ids');
    productLinksRequest('PUT', "{$shoes}/links/related", [], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('product_ids');

    $this->store->run(static function () use ($shoes): void {
        $id = Product::query()->where('public_id', $shoes)->value('id');

        expect(fn () => ProductLink::query()->create(['product_id' => $id, 'type' => ProductLinkType::Related, 'linked_product_id' => $id, 'position' => 0]))->toThrow(QueryException::class)
            ->and(ProductLink::query()->count())->toBe(0);
    });
});

it('lets staff with catalog.view see the links but only catalog.manage change them', function (): void {
    ['shoes' => $shoes, 'socks' => $socks] = $this->products;
    $viewer = storefrontStaffWith($this->store, ['catalog.view']);
    $nobody = storefrontStaffWith($this->store, []);

    productLinksRequest('GET', "{$shoes}/links", as: $viewer)->assertOk();
    productLinksRequest('PUT', "{$shoes}/links/related", ['product_ids' => [$socks]], $viewer)->assertForbidden();
    productLinksRequest('GET', "{$shoes}/links", as: $nobody)->assertForbidden();
    productLinksRequest('GET', "{$shoes}/links")->assertUnauthorized();
});

it('shows shoppers only the linked products they can buy, in the merchant\'s order, never a stock quantity', function (): void {
    ['shoes' => $shoes, 'socks' => $socks, 'laces' => $laces, 'draft' => $draft, 'unpriced' => $unpriced] = $this->products;
    $belt = $this->store->run(static fn (): string => storefrontProduct(['en' => 'Belt'], attributes: ['slug' => 'belt'])->public_id);
    // Neither oldest nor newest first: only the merchant's order gives this.
    productLinksRequest('PUT', "{$shoes}/links/cross_sell", ['product_ids' => [$draft, $laces, $belt, $unpriced, $socks]], $this->owner)->assertOk();
    $this->store->run(static fn () => stockOnHandOf(Product::query()->where('public_id', $laces)->sole()->variants()->sole()));

    $response = storefrontRequest('/products/running-shoes/links?type=cross_sell')->assertOk()->assertJsonPath('data.*.slug', ['laces', 'belt', 'wool-socks']);
    expectNoStockQuantity($response);

    storefrontRequest('/products/running-shoes/links?type=related')->assertOk()->assertJsonCount(0, 'data');
    storefrontRequest('/products/running-shoes/links')->assertUnprocessable()->assertJsonValidationErrors('type');
    storefrontRequest('/products/trail-shoes/links?type=cross_sell')->assertNotFound();

    $this->store->run(static fn () => Product::query()->where('public_id', $socks)->sole()->delete());
    storefrontRequest('/products/running-shoes/links?type=cross_sell')->assertJsonPath('data.*.slug', ['laces', 'belt']);
});

it('never links to another store\'s product', function (): void {
    $other = createStore('other-front');
    $theirs = $other->run(static fn (): string => storefrontProduct(['en' => 'Their socks'])->public_id);

    productLinksRequest('PUT', "{$this->products['shoes']}/links/related", ['product_ids' => [$theirs]], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('product_ids.0');
    productLinksRequest('GET', "{$this->products['shoes']}/links", as: $this->owner, subdomain: 'other-front')->assertUnauthorized();
});

it('replaces a product\'s links only once a change already under way has finished', function (): void {
    $shoes = $this->products['shoes'];

    $this->store->run(static function () use ($shoes): void {
        $product = Product::query()->where('public_id', $shoes)->sole();
        $other = SecondConnection::open()->holdRowLock($product);

        // It waits at its own lock on the product, before removing or adding any link.
        expect($other->blockedStatement(static fn () => app(ReplaceProductLinks::class)->handle($product, ProductLinkType::Related, [])))
            ->toStartWith('select')->toContain('"products"')->toContain('for update');
        $other->rollBack();
    });
});
