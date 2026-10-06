<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use Illuminate\Database\Seeder;
use Nnjeim\World\Actions\SeedAction;

/**
 * Loads the world's countries, states, cities, currencies, timezones and languages into the central database.
 *
 * Every store reads this shared reference data; no store database holds a copy.
 */
final class WorldSeeder extends Seeder
{
    /**
     * Seeds the world reference data.
     */
    public function run(): void
    {
        $this->call([
            SeedAction::class,
        ]);
    }
}
