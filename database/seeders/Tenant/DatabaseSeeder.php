<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

/**
 * Fills a newly created store's database with the data every store starts with.
 *
 * Runs automatically when a store is created (see TenancyServiceProvider).
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a store database.
     */
    public function run(): void
    {
        $this->call([
            StaffPermissionSeeder::class,
            StaffRoleSeeder::class,
        ]);
    }
}
