<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Listeners;

use Sentry\State\Scope;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Events\TenancyBootstrapped;

use function Sentry\configureScope;

/**
 * Labels every error report with the store it happened in, so problems can be traced to a store.
 */
final class TagErrorReportsWithTenantWhenTenancyBootstraps
{
    /**
     * Adds the store's ID as a tag on all error reports sent from now on.
     */
    public function handle(TenancyBootstrapped $event): void
    {
        $tenant = $event->tenancy->tenant;

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenantKey = (string) $tenant->getTenantKey();

        configureScope(static function (Scope $scope) use ($tenantKey): void {
            $scope->setTag('tenant_id', $tenantKey);
        });
    }
}
