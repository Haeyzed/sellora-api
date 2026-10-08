<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Landlord\Identity\Actions\SyncPlatformPermissions;
use Illuminate\Database\Seeder;

/**
 * Writes the platform permissions defined in code into the central database, so they can be given to platform roles and admins. Safe to run again; permissions:sync does the same on deploy.
 */
final class PlatformPermissionSeeder extends Seeder
{
    public function run(SyncPlatformPermissions $syncPlatformPermissions): void
    {
        $syncPlatformPermissions->handle();
    }
}
