<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreStockReservations;
use Illuminate\Console\Command;
use Throwable;

/**
 * Gives back every store's expired stock holds, such as checkouts left open, so their stock can be sold again.
 *
 * Runs every few minutes. Reserving stock also gives back expired holds on
 * the same variants first, so a buyer is never blocked by one the sweep
 * hasn't reached yet. Stores without a database are skipped; one store
 * failing never stops the others.
 */
final class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired-reservations';

    protected $description = 'Give back the expired stock holds of every store';

    public function handle(StoreStockReservations $storeStockReservations): int
    {
        $failures = 0;

        foreach (Tenant::query()->whereNotIn('status', TenantStatus::withoutDatabase())->lazyById(100) as $tenant) {
            try {
                $released = $tenant->run(static fn (): int => $storeStockReservations->releaseExpired());
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Store {$tenant->id}: {$exception->getMessage()}");
                $failures++;

                continue;
            }

            if ($released > 0) {
                $this->components->twoColumnDetail("Store {$tenant->id}", "{$released} expired holds given back");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
