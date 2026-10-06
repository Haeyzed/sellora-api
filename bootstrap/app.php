<?php

declare(strict_types=1);

use App\Landlord\Identity\CreatePlatformAdminCommand;
use App\Shared\Exceptions\ApiErrorRenderer;
use App\Shared\Features\Http\Middleware\EnsureIntegrationIsEnabled;
use App\Shared\Features\Http\Middleware\EnsureModuleIsEnabled;
use App\Shared\Http\Middleware\AddSecurityHeaders;
use App\Shared\Http\Middleware\ForceJsonResponse;
use App\Shared\Idempotency\EnsureRequestIsIdempotent;
use App\Shared\Retention\PurgeExpiredRecordsCommand;
use App\Shared\Tenancy\Http\Middleware\EnsureCentralDomain;
use App\Shared\Tenancy\TenantRoutes;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
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
                ->prefix('api/v1')
                ->name('registration.')
                ->group(base_path('routes/registration.php'));

            Route::middleware(['api', EnsureCentralDomain::class])
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));

            Route::middleware(TenantRoutes::MIDDLEWARE)
                ->prefix(TenantRoutes::PREFIX)
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withCommands([
        CreatePlatformAdminCommand::class,
        PurgeExpiredRecordsCommand::class,
    ])
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->prepend(ForceJsonResponse::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->alias([
            'idempotent' => EnsureRequestIsIdempotent::class,
            'module' => EnsureModuleIsEnabled::class,
            'integration' => EnsureIntegrationIsEnabled::class,
        ]);
        $middleware->appendToPriorityList(Authenticate::class, EnsureRequestIsIdempotent::class);

        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->render(static fn (Throwable $exception): Response => app(ApiErrorRenderer::class)->render($exception));
    })->create()
    // Every config file in config/ is complete. Merging Laravel's defaults would
    // silently add a "web" guard, a "users" provider pointing to a User model
    // that doesn't exist, and database connections this app never uses.
    ->dontMergeFrameworkConfiguration();
