<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| For RouteGuardsTest.php
|--------------------------------------------------------------------------
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
