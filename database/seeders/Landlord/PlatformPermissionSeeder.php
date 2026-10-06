<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the platform permissions, so they can be given to platform roles. Safe to run again.
 */
final class PlatformPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlatformPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, PlatformAdmin::GUARD);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
