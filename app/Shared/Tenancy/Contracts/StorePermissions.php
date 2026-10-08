<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Auth\PermissionSyncResult;

/**
 * Writes the staff permissions defined in code into a store's database, while tenancy is initialized for that store.
 *
 * Implemented by Tenant\Identity, which knows every staff permission, so
 * the platform side syncing stores never writes to a store's database
 * itself. Safe to call any number of times.
 */
interface StorePermissions
{
    public function sync(): PermissionSyncResult;
}
