<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\PermissionSync;
use App\Shared\Auth\PermissionSyncResult;

/**
 * Writes every platform permission defined in PlatformPermission into the central database, so platform roles and admins can be given them (section 10).
 */
final readonly class SyncPlatformPermissions
{
    public function __construct(private PermissionSync $permissionSync) {}

    public function handle(): PermissionSyncResult
    {
        return $this->permissionSync->sync(
            PlatformAdmin::GUARD,
            array_map(static fn (PlatformPermission $permission): string => $permission->value, PlatformPermission::cases()),
        );
    }
}
