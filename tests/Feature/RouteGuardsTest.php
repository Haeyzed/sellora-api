<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use Illuminate\Support\Facades\Route;

/*
 * Section 10: every protected route names its guard, and platform and store
 * routes only ever use their own guards. Checked against every registered
 * route, so a new route can't slip through.
 */

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

it('requires two-factor authentication on every signed-in platform route except those needed to set it up', function (): void {
    $availableBeforeSetup = [
        'platform.auth.me',
        'platform.auth.tokens.destroy',
        'platform.auth.two-factor.store',
        'platform.auth.two-factor.confirmation.store',
    ];
    $checkedRoutes = 0;

    foreach (applicationRoutes() as $route) {
        if (! in_array('auth:platform', authMiddlewareOf($route), true) || in_array($route->getName(), $availableBeforeSetup, true)) {
            continue;
        }

        expect(in_array(EnsureTwoFactorIsEnabled::class, $route->gatherMiddleware(), true))->toBeTrue("{$route->methods()[0]} {$route->uri()} must require two-factor authentication");
        $checkedRoutes++;
    }

    expect($checkedRoutes)->toBeGreaterThan(0);
});

it('applies the store\'s two-factor requirement on every signed-in staff route except those needed to set it up', function (): void {
    $availableBeforeSetup = [
        'staff.auth.me',
        'staff.auth.tokens.destroy',
        'staff.auth.two-factor.store',
        'staff.auth.two-factor.confirmation.store',
    ];
    $checkedRoutes = 0;

    foreach (applicationRoutes() as $route) {
        if (! in_array('auth:staff', authMiddlewareOf($route), true) || in_array($route->getName(), $availableBeforeSetup, true)) {
            continue;
        }

        expect(in_array(EnsureTwoFactorWhenRequired::class, $route->gatherMiddleware(), true))->toBeTrue("{$route->methods()[0]} {$route->uri()} must apply the store's two-factor requirement");
        $checkedRoutes++;
    }

    expect($checkedRoutes)->toBeGreaterThan(0);
});

it('has at least one route per guard, so the checks above really ran', function (): void {
    $usedAuthMiddleware = array_unique(array_merge(...array_map(authMiddlewareOf(...), applicationRoutes())));

    expect($usedAuthMiddleware)->toContain('auth:platform', 'auth:staff', 'auth:customer', 'auth:driver');
});
