<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use Stancl\Tenancy\Commands\Run;

/**
 * Runs an artisan command inside every store with a database (tenants:run). Skips stores that have no database yet; see RunsOnStoresWithADatabase.
 */
final class RunInStoresCommand extends Run
{
    use RunsOnStoresWithADatabase;

    public function handle(): mixed
    {
        if (! $this->targetStoresWithADatabase()) {
            return self::SUCCESS;
        }

        return parent::handle();
    }
}
