<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/*
 * The API docs' "Try it" sends each request where that API is served: the
 * platform and registration APIs to the central domain, the store API to
 * the store subdomain the reader fills in.
 */

/**
 * @return list<array<string, mixed>>
 */
function documentedServers(string $api): array
{
    $document = app(Generator::class)(Scramble::getGeneratorConfig($api));

    return $document['servers'];
}

it('sends the central APIs to the central domain and the store API to a store subdomain the reader chooses', function (): void {
    $central = 'http://'.config()->array('platform.central_domains')[0];

    expect(documentedServers('platform'))->toBe([['url' => "{$central}/api/v1/platform", 'description' => 'Central']])
        ->and(documentedServers('registration'))->toBe([['url' => "{$central}/api/v1", 'description' => 'Central']]);

    $store = documentedServers('default');
    expect($store[0]['url'])->toBe('http://{store}.'.config('platform.domain').'/api/v1')
        ->and($store[0]['variables']['store']['default'])->toBe('your-store');
});

/*
 * Section 11: every list is paged by page number or by cursor, chosen when
 * it is built, and its docs say which. Changing a list's type later breaks
 * API clients, so this table is the record: a new list must be added here.
 */

const PAGE_NUMBER_LISTS = [
    'default' => [
        '/staff/team/members', '/staff/team/roles', '/staff/team/invitations',
        '/staff/catalog/brands', '/staff/catalog/categories', '/staff/catalog/attributes',
        '/catalog/brands', '/catalog/categories',
        '/staff/inventory/locations',
        '/staff/pricing/tax-classes', '/staff/pricing/tax-rates',
    ],
    'platform' => [
        '/team/admins', '/team/roles', '/team/invitations',
        '/stores', '/stores/{store}/feature-grants', '/stores/{store}/exports',
        '/legal-documents',
    ],
];

const CURSOR_LISTS = [
    'default' => ['/staff/catalog/products', '/catalog/products', '/staff/inventory/levels', '/staff/inventory/variants/{variant}/movements'],
    'platform' => [],
];

it('documents every list as paged by page number or by cursor, never both and never neither', function (): void {
    foreach (['default', 'platform'] as $api) {
        $document = app(Generator::class)(Scramble::getGeneratorConfig($api));
        $found = ['page' => [], 'cursor' => []];

        foreach ($document['paths'] as $path => $operations) {
            $list = $operations['get'] ?? null;
            $meta = $list['responses'][200]['content']['application/json']['schema']['properties']['meta']['properties'] ?? null;
            $parameters = array_column($list['parameters'] ?? [], 'name');

            if ($meta === null && ! in_array('per_page', $parameters, true)) {
                continue;
            }

            $type = in_array('page', $parameters, true) ? 'page' : 'cursor';
            $found[$type][] = $path;

            if ($type === 'page') {
                expect($meta)->toHaveKeys(['total', 'last_page', 'current_page'], "{$path} is paged by number")->not->toHaveKey('next_cursor')
                    ->and($parameters)->not->toContain('cursor')
                    ->and($list['description'])->toContain('Paged by page number');
            } else {
                expect($meta)->toHaveKeys(['next_cursor', 'prev_cursor'], "{$path} is paged by cursor")->not->toHaveKey('total')
                    ->and($parameters)->toContain('cursor')
                    ->and($list['description'])->toContain('Paged by cursor');
            }

            expect($list['description'])->toContain('Up to '.config()->integer('api.pagination.max_per_page').' records per page');
        }

        expect($found['page'])->toEqualCanonicalizing(PAGE_NUMBER_LISTS[$api])
            ->and($found['cursor'])->toEqualCanonicalizing(CURSOR_LISTS[$api]);
    }
});

/*
 * Section 11: the docs are for development. Outside a developer's machine
 * they are shown only to platform admins signed in with two-factor
 * authentication, through their bearer token.
 */

const API_DOCS_PAGES = ['/docs/api', '/docs/api.json', '/docs/platform', '/docs/platform.json', '/docs/registration', '/docs/registration.json'];

it('shows the API docs to anyone on a developer\'s machine', function (): void {
    app()->detectEnvironment(static fn (): string => 'local');

    foreach (API_DOCS_PAGES as $page) {
        $this->get(centralUrl($page))->assertOk();
    }
});

it('shows the API docs elsewhere only to a platform admin signed in with two-factor authentication', function (): void {
    app()->detectEnvironment(static fn (): string => 'production');
    $tokenOf = static fn (PlatformAdmin $admin): string => app(AccessTokenIssuer::class)->issue($admin, PlatformAdmin::GUARD, 'test')->plainTextToken;

    $withoutTwoFactor = PlatformAdmin::factory()->create();
    $withTwoFactor = PlatformAdmin::factory()->create();
    enableTwoFactor($withTwoFactor);

    foreach (API_DOCS_PAGES as $page) {
        forgetSignIns();
        $this->get(centralUrl($page))->assertForbidden();
        forgetSignIns();
        $this->withToken($tokenOf($withoutTwoFactor))->get(centralUrl($page))->assertForbidden();
        forgetSignIns();
        $this->withToken($tokenOf($withTwoFactor))->get(centralUrl($page))->assertOk();
        $this->withoutToken();
    }
});
