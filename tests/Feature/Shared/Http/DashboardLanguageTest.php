<?php

declare(strict_types=1);

use App\Shared\Http\Middleware\UseDashboardLanguage;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Routing\Route as RouteDefinition;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Staff and platform dashboards are answered in the language they ask for
 * (Accept-Language), among every language the application has: errors and
 * validation messages included. Store content keeps the store's language,
 * and customers' and the storefront's routes keep the store-language rules.
 */

uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    // The store publishes in English only, so French is a dashboard language here, never a content one.
    $this->store = createStore('first-store');
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('puts every staff and platform route, and only those, in the dashboard language', function (): void {
    $isDashboardRoute = static fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'api/v1/staff') || str_starts_with($route->uri(), 'api/v1/platform');
    $usesDashboardLanguage = static fn (RouteDefinition $route): bool => in_array(UseDashboardLanguage::class, $route->gatherMiddleware(), true);
    $wrong = array_values(array_map(
        static fn (RouteDefinition $route): string => implode('|', $route->methods()).' '.$route->uri(),
        array_filter(applicationRoutes(), static fn (RouteDefinition $route): bool => $isDashboardRoute($route) !== $usesDashboardLanguage($route)),
    ));

    expect(array_filter(applicationRoutes(), $isDashboardRoute))->not->toBeEmpty()
        ->and($wrong)->toBe([]);
});

it('answers a staff error in French when the dashboard asks for French, also where store content follows the store language', function (string $acceptLanguage): void {
    $this->withHeader('Accept-Language', $acceptLanguage);

    teamRequest('POST', 'catalog/brands', [], $this->owner)
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'fr')
        ->assertJsonPath('errors.name.0', static fn (string $message): bool => str_starts_with($message, 'Le champ'));
})->with(['fr', 'fr-CA', 'de;q=0.1, fr-FR;q=0.9']);

it('answers in the default language when the dashboard asks for one the application doesn\'t have', function (): void {
    $this->withHeader('Accept-Language', 'xx');

    teamRequest('POST', 'catalog/brands', [], $this->owner)
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('errors.name.0', static fn (string $message): bool => str_starts_with($message, 'The '));
});

it('leaves the storefront in the store\'s language, whatever the client asks for', function (): void {
    $this->withHeader('Accept-Language', 'fr');

    $this->getJson(storeUrl('first-store', '/api/v1/catalog/products?search=a'))
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('errors.search.0', static fn (string $message): bool => str_starts_with($message, 'The '));
});

it('answers a platform error in the dashboard\'s language', function (): void {
    $this->withHeader('Accept-Language', 'fr');

    $this->postJson(centralUrl('/api/v1/platform/auth/tokens'), [])
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'fr')
        ->assertJsonPath('errors.email.0', static fn (string $message): bool => str_starts_with($message, 'Le champ'));
});
