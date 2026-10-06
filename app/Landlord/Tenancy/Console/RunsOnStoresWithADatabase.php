<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Makes a stancl/tenancy command run across every store with a database, instead of every store, when no store is named.
 *
 * A store still being set up, or whose setup failed, may have no database,
 * and one missing database would stop a deploy halfway. Suspended stores keep
 * their databases, so they stay included and reactivating them never meets
 * an out-of-date database. Naming stores with --tenants runs on exactly those,
 * which is how setting up a new store migrates it.
 *
 * @mixin Command
 */
trait RunsOnStoresWithADatabase
{
    /**
     * Fills --tenants with the stores that have a database, unless stores were named.
     *
     * @return bool False when no store has a database, so there is nothing to run.
     */
    private function targetStoresWithADatabase(): bool
    {
        if ($this->option('tenants') !== []) {
            return true;
        }

        $storeIds = Tenant::query()
            ->whereNotIn('status', TenantStatus::withoutDatabase())
            ->orderBy('created_at')
            ->pluck('id')
            ->all();

        // stancl/tenancy treats an empty list as "every store", so it is never passed one.
        if ($storeIds === []) {
            $this->components->info('No store has a database yet.');

            return false;
        }

        $this->input->setOption('tenants', $storeIds);

        return true;
    }
}
