<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Creates the built-in staff roles in a store's database. Safe to run again.
 */
final class StaffRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StaffRole::cases() as $staffRole) {
            Role::findOrCreate($staffRole->value, StaffMember::GUARD);
        }
    }
}
