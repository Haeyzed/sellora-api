<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Listeners;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\RevertedToCentralContext;

/**
 * Switches the role and permission cache back to the platform's own, once a store's work is finished.
 *
 * Also drops any store roles still held in memory, so queue workers and
 * long-running processes never carry them into the next request.
 */
final readonly class RestoreCentralPermissionCacheWhenTenancyEnds
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    /**
     * Restores the platform permission cache key and clears loaded permissions.
     */
    public function handle(RevertedToCentralContext $event): void
    {
        $this->permissionRegistrar->initializeCache();
    }
}
