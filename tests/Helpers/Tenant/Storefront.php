<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For the Storefront tests
|--------------------------------------------------------------------------
*/

/**
 * Caches in Redis, as production does, so the storefront's cache lasts from one request to the next and the tenancy package's per-store tags are really used. The array cache the other tests use starts empty every time a store's context starts, so it would hide both.
 */
function useRedisCache(): void
{
    config(['cache.default' => 'redis']);
}

/**
 * Adds a page through the staff API as the store's owner (test()->owner), published when asked, and returns it as the API shows it.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function addStorefrontPage(array $data = [], bool $publish = false, string $subdomain = 'front-store'): array
{
    $page = storefrontStaffRequest('POST', 'pages', [...['title' => ['en' => 'About us']], ...$data], as: test()->owner, subdomain: $subdomain)->assertCreated()->json('data');

    if ($publish) {
        $page = storefrontStaffRequest('POST', "pages/{$page['id']}/publish", as: test()->owner, subdomain: $subdomain)->assertOk()->json('data');
    }

    return $page;
}

/**
 * Adds a menu through the staff API as the store's owner (test()->owner), with the links given, and returns it as the API shows it.
 *
 * @param  list<array<string, mixed>>  $items  Built with menuLink().
 * @return array<string, mixed>
 */
function addStorefrontMenu(string $key = 'main', array $items = [], string $subdomain = 'front-store'): array
{
    $menu = storefrontStaffRequest('POST', 'menus', ['key' => $key, 'name' => ucfirst($key)], as: test()->owner, subdomain: $subdomain)->assertCreated()->json('data');

    if ($items === []) {
        return $menu;
    }

    return storefrontStaffRequest('PUT', "menus/{$menu['id']}/items", ['items' => $items], as: test()->owner, subdomain: $subdomain)->assertOk()->json('data');
}

/**
 * One menu link as staff send it: to a page, category, brand or product by its public ID, or to an address for type "url".
 *
 * @param  array<string, string>|string  $label  By language, or English text.
 * @param  list<array<string, mixed>>  $children
 * @return array<string, mixed>
 */
function menuLink(array|string $label, string $type, ?string $target = null, ?string $url = null, array $children = []): array
{
    return array_filter([
        'label' => is_string($label) ? ['en' => $label] : $label,
        'type' => $type,
        'target' => $target,
        'url' => $url,
        'children' => $children,
    ], static fn (mixed $value): bool => $value !== null && $value !== []);
}

/**
 * The address the current test store serves an image at, such as one uploaded to a page, for writing into a body.
 */
function storefrontImageAddress(string $subdomain = 'front-store', string $file = '1/hero.webp'): string
{
    return storeUrl($subdomain, '/tenancy/assets/'.$file);
}

/**
 * A store for the storefront tests, with room for its images, and its owner. Sets test()->store; needs test()->featureSource.
 *
 * @return array{store: Tenant, owner: StaffMember}
 */
function storefrontStore(string $subdomain = 'front-store'): array
{
    $store = createStore($subdomain, ['country_code' => 'NG']);
    test()->featureSource->give($store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['storage_in_megabytes' => 100]));
    app(App\Shared\Features\Features::class)->forget($store->getTenantKey());
    test()->store = $store;
    $owner = $store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    return ['store' => $store, 'owner' => $owner];
}

/**
 * A staff member of the given store holding a role with exactly these permissions, for the storefront tests.
 *
 * @param  list<string>  $permissions
 */
function storefrontStaffWith(Tenant $store, array $permissions): StaffMember
{
    return $store->run(static function () use ($permissions): StaffMember {
        $role = Role::findOrCreate('Storefront '.implode(' ', $permissions ?: ['none']), StaffMember::GUARD);
        $role->syncPermissions(array_map(static fn (string $permission) => Permission::findOrCreate($permission, StaffMember::GUARD), $permissions));
        $staffMember = StaffMember::factory()->create();
        $staffMember->assignRole($role);

        return $staffMember;
    });
}

/**
 * A staff request under /api/v1/staff/storefront. Uploads (a POST with a file) go as multipart form data; everything else as JSON.
 *
 * @param  array<string, mixed>  $data
 */
function storefrontStaffRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'front-store'): TestResponse
{
    forgetSignIns();
    $request = $as === null ? test()->withoutToken() : test()->withToken(staffTokenFor($as, $subdomain));
    $url = storeUrl($subdomain, '/api/v1/staff/storefront/'.ltrim($path, '/'));
    $hasFile = array_filter($data, static fn (mixed $value): bool => $value instanceof Illuminate\Http\UploadedFile) !== [];

    $response = $hasFile ? $request->post($url, $data, ['Accept' => 'application/json']) : $request->json($method, $url, $data);
    test()->flushHeaders();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * A shopper's request under /api/v1/storefront, with no sign-in, in the language given.
 */
function shopperRequest(string $path, ?string $acceptLanguage = null, string $subdomain = 'front-store'): TestResponse
{
    forgetSignIns();
    $response = test()->withoutToken()->withHeaders(array_filter(['Accept-Language' => $acceptLanguage]))->getJson(storeUrl($subdomain, '/api/v1/storefront/'.ltrim($path, '/')));
    test()->flushHeaders();
    tenancy()->end();

    return $response;
}
