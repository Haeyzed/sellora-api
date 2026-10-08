<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Auth\PermissionSync;
use App\Shared\Auth\PermissionSyncResult;
use App\Shared\Tenancy\Contracts\StorePermissions;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Writes every staff permission in the catalogue into the current store's database, so roles and people can be given them (section 10).
 */
final readonly class StaffPermissionSync implements StorePermissions
{
    public function __construct(
        private StaffPermissionCatalogue $permissionCatalogue,
        private PermissionSync $permissionSync,
    ) {}

    public function sync(): PermissionSyncResult
    {
        return $this->permissionSync->sync(StaffMember::GUARD, $this->permissionCatalogue->all());
    }
}
