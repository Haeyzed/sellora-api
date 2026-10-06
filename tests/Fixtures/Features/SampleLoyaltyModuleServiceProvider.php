<?php

declare(strict_types=1);

namespace Tests\Fixtures\Features;

use App\Shared\Features\ModuleServiceProvider;

/**
 * A stand-in module with routes, used to test plan gating before any real module exists.
 */
final class SampleLoyaltyModuleServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadTenantRoutesFrom(__DIR__.'/sample-loyalty-routes.php');
    }

    protected function key(): string
    {
        return 'loyalty';
    }

    protected function windDownRouteNames(): array
    {
        return ['loyalty.points.redeem'];
    }
}
