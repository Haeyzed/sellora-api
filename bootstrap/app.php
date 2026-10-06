<?php

declare(strict_types=1);

use App\Shared\Exceptions\ApiErrorRenderer;
use App\Shared\Http\Middleware\AddSecurityHeaders;
use App\Shared\Http\Middleware\ForceJsonResponse;
use App\Shared\Tenancy\Http\Middleware\EnsureCentralDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::middleware(['api', EnsureCentralDomain::class])
                ->prefix('api/v1/platform')
                ->name('platform.')
                ->group(base_path('routes/landlord.php'));

            Route::middleware(['api', EnsureCentralDomain::class])
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));

            Route::middleware(['api', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
                ->prefix('api/v1')
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->prepend(ForceJsonResponse::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->render(static fn (Throwable $exception): Response => app(ApiErrorRenderer::class)->render($exception));
    })->create();
