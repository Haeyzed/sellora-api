<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
 * Section 10: every protected route names its guard, and platform and store
 * routes only ever use their own guards. Checked against every registered
 * route, so a new route can't slip through.
 */

/**
 * @return list<RouteDefinition>
 */
function applicationRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        static fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'api/') || str_starts_with($route->uri(), 'webhooks'),
    ));
}

/**
 * @return list<string>
 */
function authMiddlewareOf(RouteDefinition $route): array
{
    return array_values(array_filter(
        $route->gatherMiddleware(),
        static fn (mixed $middleware): bool => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:')),
    ));
}

it('never protects a route with a bare auth or auth:sanctum middleware', function (): void {
    foreach (applicationRoutes() as $route) {
        foreach (authMiddlewareOf($route) as $authMiddleware) {
            expect($authMiddleware)->not->toBeIn(['auth', 'auth:sanctum'], "Route {$route->uri()} uses {$authMiddleware}");
        }
    }
});

it('uses only the platform guard on platform routes, and only store guards on store routes', function (): void {
    foreach (applicationRoutes() as $route) {
        $allowedAuthMiddleware = str_starts_with($route->uri(), 'api/v1/platform')
            ? ['auth:platform']
            : ['auth:staff', 'auth:customer', 'auth:driver'];

        foreach (authMiddlewareOf($route) as $authMiddleware) {
            expect($authMiddleware)->toBeIn($allowedAuthMiddleware, "Route {$route->uri()} uses {$authMiddleware}");
        }
    }
});

it('has at least one route per guard, so the checks above really ran', function (): void {
    $usedAuthMiddleware = array_unique(array_merge(...array_map(authMiddlewareOf(...), applicationRoutes())));

    expect($usedAuthMiddleware)->toContain('auth:platform', 'auth:staff', 'auth:customer', 'auth:driver');
});
