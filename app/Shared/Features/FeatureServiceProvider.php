<?php

declare(strict_types=1);

namespace App\Shared\Features;

use App\Shared\Tenancy\TenantRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The shared parent of module and integration service providers: declares the feature and wires its routes and tables.
 *
 * Every provider is always registered in bootstrap/providers.php, whatever
 * any store's plan; access is decided per request by Features. Subclasses
 * add their own bindings in registerServices() and their routes, listeners
 * and config in boot().
 */
abstract class FeatureServiceProvider extends ServiceProvider
{
    /**
     * The key plans use to include this feature, such as "hr" or "whatsapp".
     */
    abstract protected function key(): string;

    abstract protected function kind(): FeatureKind;

    /**
     * Features that must be usable for this one to be usable.
     *
     * @return list<string>
     */
    protected function requiredFeatureKeys(): array
    {
        return [];
    }

    /**
     * Route names that stay usable while the feature is locked or suspended, so customers can finish what they started.
     *
     * @return list<string>
     */
    protected function windDownRouteNames(): array
    {
        return [];
    }

    /**
     * Bindings the feature needs, registered alongside its declaration.
     */
    protected function registerServices(): void {}

    final public function register(): void
    {
        $this->callAfterResolving(FeatureRegistry::class, function (FeatureRegistry $featureRegistry): void {
            $featureRegistry->register(new FeatureDefinition(
                key: $this->key(),
                kind: $this->kind(),
                requiredFeatureKeys: $this->requiredFeatureKeys(),
                windDownRouteNames: $this->windDownRouteNames(),
            ));
        });

        $this->registerServices();
    }

    /**
     * Serves the feature's store routes under /api/v1 on store domains, named "<key>." and guarded by its plan gate.
     */
    protected function loadTenantRoutesFrom(string $routesPath): void
    {
        Route::middleware([...TenantRoutes::MIDDLEWARE, $this->kind()->value.':'.$this->key()])
            ->prefix(TenantRoutes::PREFIX)
            ->name($this->key().'.')
            ->group($routesPath);
    }

    /**
     * Adds the feature's migrations to every store database. They run for every store, whatever its plan.
     */
    protected function loadTenantMigrationsFrom(string $migrationsPath): void
    {
        $this->app->make('config')->push('tenancy.migration_parameters.--path', $migrationsPath);
    }
}
