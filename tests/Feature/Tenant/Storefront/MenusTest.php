<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Storefront\Actions\ReplaceMenuItems;
use App\Tenant\Storefront\Data\MenuItemData;
use App\Tenant\Storefront\Enums\MenuLinkType;
use App\Tenant\Storefront\Models\Menu;
use App\Tenant\Storefront\Models\MenuItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: the store's menus, for staff. Staff with storefront.view see
 * them; with storefront.manage they add, rename and delete them and replace
 * their links, two levels deep and at most 100 in all. Each link goes to
 * exactly one page, category, brand, product or address; the database
 * refuses anything else.
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

it('adds, lists, renames and deletes menus, which staff with storefront.view only see', function (): void {
    $viewer = storefrontStaffWith($this->store, ['storefront.view']);

    $menu = addStorefrontMenu('footer');
    expect($menu)->toMatchArray(['key' => 'footer', 'name' => 'Footer', 'items' => []]);

    storefrontStaffRequest('GET', 'menus', as: $viewer)->assertOk()->assertJsonPath('data.0.key', 'footer')->assertJsonMissingPath('data.0.items')->assertJsonPath('meta.total', 1);
    storefrontStaffRequest('GET', "menus/{$menu['id']}", as: $viewer)->assertOk()->assertJsonPath('data.name', 'Footer');
    storefrontStaffRequest('POST', 'menus', ['key' => 'main', 'name' => 'Main'], as: $viewer)->assertForbidden();
    storefrontStaffRequest('PUT', "menus/{$menu['id']}/items", ['items' => []], as: $viewer)->assertForbidden();
    storefrontStaffRequest('DELETE', "menus/{$menu['id']}", as: $viewer)->assertForbidden();
    storefrontStaffRequest('GET', 'menus')->assertUnauthorized();

    storefrontStaffRequest('PATCH', "menus/{$menu['id']}", ['key' => 'footer-help', 'name' => 'Help'], as: $this->owner)->assertOk()->assertJsonPath('data.key', 'footer-help')->assertJsonPath('data.name', 'Help');
    storefrontStaffRequest('DELETE', "menus/{$menu['id']}", as: $this->owner)->assertNoContent();
    storefrontStaffRequest('GET', "menus/{$menu['id']}", as: $this->owner)->assertNotFound();
});

it('refuses a key another menu uses, or one that isn\'t lower-case letters, numbers and single hyphens', function (): void {
    $footer = addStorefrontMenu('footer');
    addStorefrontMenu('main');

    storefrontStaffRequest('POST', 'menus', ['key' => 'footer', 'name' => 'Again'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('key');
    storefrontStaffRequest('PATCH', "menus/{$footer['id']}", ['key' => 'main'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('key');
    storefrontStaffRequest('PATCH', "menus/{$footer['id']}", ['key' => 'footer'], as: $this->owner)->assertOk();

    foreach (['Main', 'main menu', 'main--menu', '-main'] as $key) {
        storefrontStaffRequest('POST', 'menus', ['key' => $key, 'name' => 'Bad'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('key');
    }
});

it('replaces a menu\'s links two levels deep, in order, with their labels by language', function (): void {
    $page = addStorefrontPage(['title' => ['en' => 'About us']]);
    $category = $this->store->run(static fn (): Category => Category::factory()->create(['name' => ['en' => 'Shirts']]));

    $menu = addStorefrontMenu('main', [
        menuLink(['en' => 'Shop', 'fr' => 'Boutique'], 'category', target: $category->public_id, children: [
            menuLink('Sale', 'url', url: '/sale'),
            menuLink('Call us', 'url', url: 'tel:+2348012345678'),
        ]),
        menuLink('About', 'page', target: $page['id']),
    ]);

    expect($menu['items'])->toBe([
        ['label' => ['en' => 'Shop', 'fr' => 'Boutique'], 'type' => 'category', 'target' => $category->public_id, 'url' => null, 'opens_in_new_tab' => false, 'children' => [
            ['label' => ['en' => 'Sale'], 'type' => 'url', 'target' => null, 'url' => '/sale', 'opens_in_new_tab' => false, 'children' => []],
            ['label' => ['en' => 'Call us'], 'type' => 'url', 'target' => null, 'url' => 'tel:+2348012345678', 'opens_in_new_tab' => false, 'children' => []],
        ]],
        ['label' => ['en' => 'About'], 'type' => 'page', 'target' => $page['id'], 'url' => null, 'opens_in_new_tab' => false, 'children' => []],
    ]);

    storefrontStaffRequest('PUT', "menus/{$menu['id']}/items", ['items' => [
        [...menuLink('Blog', 'url', url: 'https://blog.example.com'), 'opens_in_new_tab' => true],
    ]], as: $this->owner)->assertOk()->assertJsonPath('data.items.0.opens_in_new_tab', true)->assertJsonCount(1, 'data.items');

    expect($this->store->run(static fn (): int => MenuItem::query()->count()))->toBe(1);
});

it('refuses a third level, more than 100 links in all, an address of another kind, and a label without the default language', function (): void {
    $menu = addStorefrontMenu('main');
    $replace = fn (array $items) => storefrontStaffRequest('PUT', "menus/{$menu['id']}/items", ['items' => $items], as: $this->owner);

    $replace([menuLink('One', 'url', url: '/one', children: [menuLink('Two', 'url', url: '/two', children: [menuLink('Three', 'url', url: '/three')])])])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.children.0.children');
    $replace(array_fill(0, 10, menuLink('Group', 'url', url: '/group', children: array_fill(0, 10, menuLink('Link', 'url', url: '/link')))))
        ->assertUnprocessable()->assertJsonValidationErrors('items');
    $replace(array_fill(0, 101, menuLink('Link', 'url', url: '/link')))->assertUnprocessable()->assertJsonValidationErrors('items');

    foreach (['javascript:alert(1)', 'http://example.com', '//evil.example.com', 'data:text/html,hi', 'sale', '/a b'] as $url) {
        $replace([menuLink('Bad', 'url', url: $url)])->assertUnprocessable()->assertJsonValidationErrors('items.0.url');
    }

    $replace([menuLink('Both', 'url', target: '01JAAAAAAAAAAAAAAAAAAAAAAA', url: '/x')])->assertUnprocessable()->assertJsonValidationErrors('items.0.target');
    $replace([menuLink('None', 'page')])->assertUnprocessable()->assertJsonValidationErrors('items.0.target');
    $replace([menuLink(['fr' => 'Seulement'], 'url', url: '/x')])->assertUnprocessable()->assertJsonValidationErrors('items.0.label');

    $replace(array_fill(0, 10, menuLink('Group', 'url', url: '/group', children: array_fill(0, 9, menuLink('Link', 'url', url: '/link')))))->assertOk();
});

it('refuses a link to something missing or in the trash, pointing at that link, and keeps the menu as it was', function (): void {
    $brand = $this->store->run(static function (): Brand {
        $brand = Brand::factory()->create(['name' => ['en' => 'Gone']]);
        $brand->delete();

        return $brand;
    });
    $menu = addStorefrontMenu('main', [menuLink('Kept', 'url', url: '/kept')]);

    storefrontStaffRequest('PUT', "menus/{$menu['id']}/items", ['items' => [
        menuLink('Fine', 'url', url: '/fine', children: [menuLink('Gone', 'brand', target: $brand->public_id)]),
    ]], as: $this->owner)->assertUnprocessable()->assertJsonPath('code', 'menu_link_target_not_found')->assertJsonValidationErrors('items.0.children.0.target');

    storefrontStaffRequest('PUT', "menus/{$menu['id']}/items", ['items' => [menuLink('Nobody', 'product', target: '01JAAAAAAAAAAAAAAAAAAAAAAA')]], as: $this->owner)
        ->assertUnprocessable()->assertJsonPath('code', 'menu_link_target_not_found')->assertJsonValidationErrors('items.0.target');

    storefrontStaffRequest('GET', "menus/{$menu['id']}", as: $this->owner)->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.label.en', 'Kept');
});

it('still shows staff a link whose category was moved to the trash later', function (): void {
    $category = $this->store->run(static fn (): Category => Category::factory()->create(['name' => ['en' => 'Hats']]));
    $menu = addStorefrontMenu('main', [menuLink('Hats', 'category', target: $category->public_id)]);
    $this->store->run(static fn () => Category::query()->whereKey($category->id)->sole()->delete());

    storefrontStaffRequest('GET', "menus/{$menu['id']}", as: $this->owner)->assertOk()->assertJsonPath('data.items.0.target', $category->public_id);
});

it('lets the database refuse a link with no destination or two, and a link under one from another menu', function (): void {
    $main = addStorefrontMenu('main', [menuLink('Top', 'url', url: '/top')]);
    addStorefrontMenu('footer', [menuLink('Help', 'url', url: '/help')]);

    $this->store->run(static function (): void {
        $insert = static fn (array $columns) => DB::table('menu_items')->insert([
            'menu_id' => Menu::query()->where('key', 'footer')->value('id'),
            'label' => json_encode(['en' => 'Bad']),
            'position' => 5,
            ...$columns,
        ]);

        expect(fn () => $insert([]))->toThrow(QueryException::class)
            ->and(fn () => $insert(['url' => '/a', 'page_id' => null, 'category_id' => Category::factory()->create()->id]))->toThrow(QueryException::class)
            ->and(fn () => $insert(['url' => 'javascript:alert(1)']))->toThrow(QueryException::class)
            ->and(fn () => $insert(['url' => '/a', 'parent_id' => MenuItem::query()->whereHas('menu', static fn ($menus) => $menus->where('key', 'main'))->value('id')]))->toThrow(QueryException::class);
    });

    storefrontStaffRequest('DELETE', "menus/{$main['id']}", as: $this->owner)->assertNoContent();
    expect($this->store->run(static fn (): int => MenuItem::query()->count()))->toBe(1);
});

it('replaces a menu\'s links only once a change already under way has finished', function (): void {
    $menu = addStorefrontMenu('main');

    $this->store->run(static function () use ($menu): void {
        $model = Menu::query()->where('public_id', $menu['id'])->sole();
        $other = SecondConnection::open()->holdRowLock($model);

        // It waits at its own lock on the menu, before removing or adding any link, not at a later write that would wait anyway.
        expect($other->blockedStatement(static fn () => app(ReplaceMenuItems::class)->handle($model, [
            new MenuItemData(label: ['en' => 'Sale'], linkType: MenuLinkType::Url, target: null, url: '/sale', opensInNewTab: false, path: 'items.0'),
        ])))->toStartWith('select')->toContain('"menus"')->toContain('for update');
        $other->rollBack();
    });
});
