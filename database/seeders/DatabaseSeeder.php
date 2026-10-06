<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Landlord\WorldSeeder;
use Illuminate\Database\Seeder;

/**
 * Fills the central (platform) database with the data every installation needs.
 *
 * Store databases are seeded separately by Database\Seeders\Tenant\DatabaseSeeder.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the central database.
     */
    public function run(): void
    {
        $this->call([
            WorldSeeder::class,
        ]);
    }
}
