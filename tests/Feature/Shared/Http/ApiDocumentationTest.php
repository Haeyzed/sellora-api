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
