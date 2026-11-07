<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Events\InvoicesDoNotMatchSales;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreInvoiceBalances;
use App\Shared\Tenancy\Enums\InvoiceTaxProblem;
use App\Shared\Tenancy\InvoiceBalanceMismatch;
use App\Shared\Tenancy\InvoiceTaxMismatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recalculates, for every store, that each invoice less its credit notes equals the sale money kept, and that no line or rate has more tax credited than invoiced, nor tax left once the invoice is credited in full, and reports each that doesn't.
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
                if ($mismatch instanceof InvoiceTaxMismatch) {
                    $this->reportTaxMismatch($tenant->id, $mismatch);
                } else {
                    $this->reportMismatch($tenant->id, $mismatch);
                }

                $problems++;
            }

            if ($mismatches === []) {
                $this->components->twoColumnDetail("Store {$tenant->id}", 'invoices match the sale money kept');

                continue;
            }

            InvoicesDoNotMatchSales::dispatch($tenant, count($mismatches));
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

    private function reportTaxMismatch(string $storeId, InvoiceTaxMismatch $mismatch): void
    {
        $where = $mismatch->linePosition === null ? 'at rate '.$mismatch->rate : 'on line '.$mismatch->linePosition.' at rate '.$mismatch->rate;
        $what = $mismatch->problem === InvoiceTaxProblem::CreditedAboveInvoiced ? 'more tax credited than invoiced' : 'credited in full with tax left';

        // Only public IDs, the rate and amounts: staff find the rest from there.
        Log::warning('An invoice\'s credit notes carry tax that does not fit the invoice.', [
            'store' => $storeId,
            'order' => $mismatch->orderId,
            'invoice' => $mismatch->invoiceId,
            'problem' => $mismatch->problem->value,
            'line_position' => $mismatch->linePosition,
            'rate' => $mismatch->rate,
            'currency' => $mismatch->currency,
            'tax_credited' => $mismatch->credited,
            'tax_invoiced' => $mismatch->invoiced,
        ]);
        $this->components->error(sprintf(
            'Store %s, order %s, invoice %s: %s %s, %d credited of %d invoiced (%s, minor units).',
            $storeId, $mismatch->orderId, $mismatch->invoiceId, $what, $where, $mismatch->credited, $mismatch->invoiced, $mismatch->currency,
        ));
    }
}
