<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Tenancy\Listeners\RemoveTenantTagFromErrorReportsWhenTenancyEnds;
use App\Shared\Tenancy\Listeners\RestoreCentralPermissionCacheWhenTenancyEnds;
use App\Shared\Tenancy\Listeners\ScopePermissionCacheToTenantWhenTenancyBootstraps;
use App\Shared\Tenancy\Listeners\TagErrorReportsWithTenantWhenTenancyBootstraps;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * Wires multi-tenancy: what happens when a store is created or deleted, and what switches when a store's request starts or ends.
 *
 * Tenant routes are mapped in bootstrap/app.php together with all other routes.
 */
final class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Lists the tenancy events and the work that runs for each one.
     *
     * @return array<class-string, list<class-string|JobPipeline>>
     */
    public function events(): array
    {
        return [
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                ])->send(static fn (Events\TenantCreated $event): Tenant => $event->tenant)
                    ->shouldBeQueued(false),
            ],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(static fn (Events\TenantDeleted $event): Tenant => $event->tenant)
                    ->shouldBeQueued(false),
            ],

            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyBootstrapped::class => [
                ScopePermissionCacheToTenantWhenTenancyBootstraps::class,
                TagErrorReportsWithTenantWhenTenancyBootstraps::class,
            ],

            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
            Events\RevertedToCentralContext::class => [
                RestoreCentralPermissionCacheWhenTenancyEnds::class,
                RemoveTenantTagFromErrorReportsWhenTenancyEnds::class,
            ],
        ];
    }

    /**
     * Registers the tenancy event listeners and makes tenant identification run before any other middleware.
     */
    public function boot(): void
    {
        $this->bootEvents();
        $this->makeTenancyMiddlewareHighestPriority();
    }

    private function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    private function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            Middleware\PreventAccessFromCentralDomains::class,
            Middleware\InitializeTenancyByDomain::class,
        ];

        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(Kernel::class);

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
