<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider as TelescopePackageServiceProvider;
use Sentry\State\Scope;

use function Sentry\configureScope;

/**
 * Registers application-wide services that belong to no single domain.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Registers services, including the local-only debugging tools.
     */
    public function register(): void
    {
        $this->registerTelescopeLocally();
    }

    /**
     * Boots application-wide behaviour.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/landlord'));
        $this->tagErrorReportsWithGuard();
        $this->registerPlatformApiDocumentation();
    }

    /**
     * Documents the platform admin API separately from the store API, because they serve different audiences.
     *
     * The store API is Scramble's default API, configured in config/scramble.php.
     */
    private function registerPlatformApiDocumentation(): void
    {
        Scramble::registerApi('platform', [
            'api_path' => 'api/v1/platform',
            'info' => [
                'description' => 'Endpoints for platform administrators, served on the central domain only.',
            ],
            'ui' => [
                'title' => config('app.name').' Platform API',
            ],
        ])->expose(ui: 'docs/platform', document: 'docs/platform.json');
    }

    /**
     * Telescope records requests, queries and jobs, so it only ever runs on a developer's machine.
     */
    private function registerTelescopeLocally(): void
    {
        if (! $this->app->environment('local') || ! class_exists(TelescopePackageServiceProvider::class)) {
            return;
        }

        $this->app->register(TelescopePackageServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    }

    /**
     * Labels error reports with the kind of user signed in (platform, staff, customer or driver).
     */
    private function tagErrorReportsWithGuard(): void
    {
        Event::listen(static function (Authenticated $event): void {
            configureScope(static function (Scope $scope) use ($event): void {
                $scope->setTag('guard', $event->guard);
            });
        });
    }
}
