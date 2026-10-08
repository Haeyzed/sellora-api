<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Shared\Tenancy\Contracts\StorePermissions;
use Illuminate\Database\Seeder;

/**
 * Writes every staff permission defined in code into a new store's database. Safe to run again; permissions:sync does the same for every store on deploy.
 */
final class StaffPermissionSeeder extends Seeder
{
    public function run(StorePermissions $storePermissions): void
    {
        $storePermissions->sync();
    }
}
