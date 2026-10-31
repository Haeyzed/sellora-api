<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreInvoiceBalances;
use App\Shared\Tenancy\InvoiceBalanceMismatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recalculates, for every store, that each invoice less its credit notes equals the sale money kept, and reports each that doesn't.
 *
 * Runs on a schedule. It only reports, never corrects: a mismatch is a bug or
 * a hand-edited database to investigate. Each mismatch is logged as a warning
 * by public ID, and the command then ends with a failure. Stores without a
 * database are skipped; one store failing never stops the others.
 */
final class ReconcileInvoiceBalancesCommand extends Command
{
    protected $signature = 'orders:reconcile-invoices {--store= : Only this store\'s ID}';

    protected $description = 'Check every store\'s invoices, less their credit notes, against the sale money it kept and report mismatches';

    public function handle(StoreInvoiceBalances $storeInvoiceBalances): int
    {
        $problems = 0;
        $store = $this->option('store');
        $stores = Tenant::query()
            ->whereNotIn('status', TenantStatus::withoutDatabase())
            ->when(is_string($store), static fn ($query) => $query->whereKey($store));

        foreach ($stores->lazyById(100) as $tenant) {
            try {
                $mismatches = $tenant->run(static fn (): array => $storeInvoiceBalances->mismatches());
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Store {$tenant->id}: its invoices could not be checked.");
                $problems++;

                continue;
            }

            foreach ($mismatches as $mismatch) {
                $this->reportMismatch($tenant->id, $mismatch);
                $problems++;
            }

            if ($mismatches === []) {
                $this->components->twoColumnDetail("Store {$tenant->id}", 'invoices match the sale money kept');
            }
        }

        return $problems > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reportMismatch(string $storeId, InvoiceBalanceMismatch $mismatch): void
    {
        // Only public IDs and amounts: staff find the rest from there.
        Log::warning('An invoice less its credit notes does not equal the sale money kept.', [
            'store' => $storeId,
            'order' => $mismatch->orderId,
            'invoice' => $mismatch->invoiceId,
            'currency' => $mismatch->currency,
            'invoice_left' => $mismatch->invoiceLeft,
            'sale_kept' => $mismatch->saleKept,
        ]);
        $this->components->error(sprintf(
            'Store %s, order %s, invoice %s: %d left on the invoice, %d kept (%s, minor units).',
            $storeId, $mismatch->orderId, $mismatch->invoiceId, $mismatch->invoiceLeft, $mismatch->saleKept, $mismatch->currency,
        ));
    }
}
