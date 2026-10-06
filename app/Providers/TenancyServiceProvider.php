<?php

declare(strict_types=1);

namespace App\Providers;

use App\Landlord\Tenancy\Console\MigrateStoreDatabasesCommand;
use App\Landlord\Tenancy\Console\RollbackStoreDatabasesCommand;
use App\Landlord\Tenancy\Console\RunInStoresCommand;
use App\Landlord\Tenancy\Console\SeedStoreDatabasesCommand;
use App\Landlord\Tenancy\StoreDomainResolver;
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
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

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
            // Nothing runs on TenantCreated: a new store's database is created by
            // Landlord\Tenancy\Jobs\ProvisionStore on the queue, on a server in the
            // store's region, never inside the registration request.
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
     * Stores are found from their domain only while they are serving requests.
     */
    public function register(): void
    {
        $this->app->bind(DomainTenantResolver::class, StoreDomainResolver::class);
    }

    /**
     * Registers the tenancy event listeners and makes tenant identification run before any other middleware.
     */
    public function boot(): void
    {
        $this->replaceStoreCommands();
        $this->bootEvents();
        $this->makeTenancyMiddlewareHighestPriority();
    }

    /**
     * The package's commands that run across every store, replaced by versions that skip stores without a database yet. Same names, so deploy scripts don't change.
     */
    private function replaceStoreCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateStoreDatabasesCommand::class,
                SeedStoreDatabasesCommand::class,
                RollbackStoreDatabasesCommand::class,
                RunInStoresCommand::class,
            ]);
        }
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
