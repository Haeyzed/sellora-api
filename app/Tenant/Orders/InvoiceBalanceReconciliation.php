<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Tenancy\Contracts\StoreInvoiceBalances;
use App\Shared\Tenancy\InvoiceBalanceMismatch;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Services\InvoiceTaxCheck;
use App\Tenant\Orders\Services\OrderSaleKept;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recalculates, for every invoiced order in the store, that its invoice less its credit notes equals the sale money kept, and that its credit notes' tax fits the invoice's (InvoiceTaxCheck), and reports each that doesn't. Orders whose money changed within the grace period are left for the next run: a credit note still on its way (a refund that went back moments ago) would read as a mismatch. Any other change to an order, or a retry that moved nothing, never holds it back.
 */
final readonly class InvoiceBalanceReconciliation implements StoreInvoiceBalances
{
    public function __construct(
        private OrderSaleKept $orderSaleKept,
        private InvoiceTaxCheck $invoiceTaxCheck,
    ) {}

    public function mismatches(): array
    {
        $mismatches = [];
        $settledBy = CarbonImmutable::now()->subMinutes(config()->integer('orders.reconcile_grace_minutes'));

        Invoice::query()
            ->whereHas('order', static fn (Builder $order): Builder => $order->where(static fn (Builder $settled): Builder => $settled->whereNull('money_changed_at')->orWhere('money_changed_at', '<=', $settledBy)))
            ->with(['order', 'lines.taxes', 'creditNotes.lines.taxes'])
            ->chunkById(200, function (Collection $invoices) use (&$mismatches): void {
                foreach ($invoices as $invoice) {
                    $left = $invoice->total->minorAmount() - $invoice->creditNotes->sum(static fn (CreditNote $creditNote): int => $creditNote->total->minorAmount());
                    $kept = $this->orderSaleKept->for($invoice->order)->minorAmount();

                    if ($left !== $kept) {
                        $mismatches[] = new InvoiceBalanceMismatch($invoice->order->public_id, $invoice->public_id, $invoice->currency, $left, $kept);
                    }

                    array_push($mismatches, ...$this->invoiceTaxCheck->problems($invoice));
                }
            });

        return $mismatches;
    }
}
