<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Identity\Actions\SyncPlatformPermissions;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\PermissionSyncResult;
use App\Shared\Tenancy\Contracts\StorePermissions;
use Illuminate\Console\Command;
use Throwable;

/**
 * Writes the permissions defined in code into the central database (platform) and every store database (staff), as every deploy must (section 10).
 *
 * Safe to run any number of times. Stores without a database are skipped:
 * a store being set up gets its permissions from its own setup. One store
 * failing never stops the others; the command then ends with a failure, so
 * a deploy notices.
 */
final class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Write the permissions defined in code into the central database and every store database';

    public function handle(SyncPlatformPermissions $syncPlatformPermissions, StorePermissions $storePermissions): int
    {
        $this->report('Platform', $syncPlatformPermissions->handle());

        $failures = 0;

        foreach (Tenant::query()->whereNotIn('status', TenantStatus::withoutDatabase())->lazyById(100) as $store) {
            try {
                $this->report("Store {$store->id}", $store->run(static fn (): PermissionSyncResult => $storePermissions->sync()));
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Store {$store->id}: {$exception->getMessage()}");
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->components->error("{$failures} stores could not be synced; run the command again once they are fixed.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function report(string $where, PermissionSyncResult $result): void
    {
        if ($result->changedNothing()) {
            $this->components->twoColumnDetail($where, 'up to date');
        } else {
            $this->components->twoColumnDetail($where, sprintf('added %s; removed %s', $this->names($result->created), $this->names($result->removed)));
        }

        if ($result->keptInUse !== []) {
            $this->components->warn("{$where}: no longer defined in code but still held by a role or person, so kept: {$this->names($result->keptInUse)}.");
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function names(array $names): string
    {
        return $names === [] ? 'none' : implode(', ', $names);
    }
}
