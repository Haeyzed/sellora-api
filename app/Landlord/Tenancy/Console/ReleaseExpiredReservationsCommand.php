<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StorePromotionHolds;
use App\Shared\Tenancy\Contracts\StoreStockReservations;
use App\Shared\Tenancy\Contracts\StoreUnpaidOrders;
use Illuminate\Console\Command;
use Throwable;

/**
 * Gives back every store's expired holds from checkouts left open (stock, so it can be sold again, and promotion uses, so they count against no limit), and cancels unpaid orders past their payment deadline, which gives back theirs.
 *
 * Runs every few minutes. Reserving stock or a promotion use also gives back
 * expired holds on the same variants or promotion first, so a buyer is never
 * blocked by one the sweep hasn't reached yet. Stores without a database are skipped; one store
 * failing never stops the others.
 */
final class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired-reservations';

    protected $description = 'Give back expired stock holds and promotion uses, and cancel unpaid orders past their deadline, in every store';

    public function handle(StoreStockReservations $storeStockReservations, StorePromotionHolds $storePromotionHolds, StoreUnpaidOrders $storeUnpaidOrders): int
    {
        $failures = 0;

        foreach (Tenant::query()->whereNotIn('status', TenantStatus::withoutDatabase())->lazyById(100) as $tenant) {
            try {
                $released = $tenant->run(static fn (): int => $storeStockReservations->releaseExpired() + $storePromotionHolds->releaseExpired() + $storeUnpaidOrders->cancelExpired());
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
