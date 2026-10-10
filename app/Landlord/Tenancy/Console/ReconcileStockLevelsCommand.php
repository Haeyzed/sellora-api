<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreStockLedger;
use App\Shared\Tenancy\StockLevelMismatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recalculates every store's stock levels from its movement ledger and reports each level that doesn't match (section 13).
 *
 * Runs on a schedule. It only reports, never corrects: a mismatch is a bug
 * or a hand-edited database to investigate. Each mismatch is logged as a
 * warning with the store, and the command then ends with a failure. Stores
 * without a database are skipped; one store failing never stops the others.
 */
final class ReconcileStockLevelsCommand extends Command
{
    protected $signature = 'inventory:reconcile {--store= : Only this store\'s ID}';

    protected $description = 'Check every store\'s stock levels against its stock movement ledger and report mismatches';

    public function handle(StoreStockLedger $storeStockLedger): int
    {
        $problems = 0;
        $store = $this->option('store');
        $stores = Tenant::query()
            ->whereNotIn('status', TenantStatus::withoutDatabase())
            ->when(is_string($store), static fn ($query) => $query->whereKey($store));

        foreach ($stores->lazyById(100) as $tenant) {
            try {
                $mismatches = $tenant->run(static fn (): array => $storeStockLedger->mismatches());
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Store {$tenant->id}: {$exception->getMessage()}");
                $problems++;

                continue;
            }

            foreach ($mismatches as $mismatch) {
                $this->reportMismatch($tenant->id, $mismatch);
                $problems++;
            }

            if ($mismatches === []) {
                $this->components->twoColumnDetail("Store {$tenant->id}", 'matches its ledger');
            }
        }

        return $problems > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reportMismatch(string $storeId, StockLevelMismatch $mismatch): void
    {
        $context = [
            'store' => $storeId,
            'variant' => $mismatch->variantId,
            'location' => $mismatch->locationId,
            'on_hand' => $mismatch->onHand,
            'on_hand_from_ledger' => $mismatch->onHandFromLedger,
            'reserved' => $mismatch->reserved,
            'reserved_from_ledger' => $mismatch->reservedFromLedger,
        ];

        Log::warning('A stock level does not match its movement ledger.', $context);
        $this->components->error(sprintf(
            'Store %s, variant %s at %s: on hand %d (ledger %d), reserved %d (ledger %d).',
            $storeId, $mismatch->variantId, $mismatch->locationId, $mismatch->onHand, $mismatch->onHandFromLedger, $mismatch->reserved, $mismatch->reservedFromLedger,
        ));
    }
}
