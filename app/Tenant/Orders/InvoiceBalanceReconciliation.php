<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Tenancy\Contracts\StoreInvoiceBalances;
use App\Shared\Tenancy\InvoiceBalanceMismatch;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Services\OrderSaleKept;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recalculates, for every invoiced order in the store, that its invoice less its credit notes equals the sale money kept, and reports each that doesn't. A credit note still on its way (a refund that went back moments ago) reads as a mismatch until it is issued.
 */
final readonly class InvoiceBalanceReconciliation implements StoreInvoiceBalances
{
    public function __construct(private OrderSaleKept $orderSaleKept) {}

    public function mismatches(): array
    {
        $mismatches = [];

        Invoice::query()->with('order')->chunkById(200, function (Collection $invoices) use (&$mismatches): void {
            $credited = CreditNote::query()->whereIn('invoice_id', $invoices->modelKeys())->groupBy('invoice_id')->selectRaw('invoice_id, sum(total_amount) as credited')->pluck('credited', 'invoice_id');

            foreach ($invoices as $invoice) {
                $left = $invoice->total->minorAmount() - (int) ($credited[$invoice->id] ?? 0);
                $kept = $this->orderSaleKept->for($invoice->order)->minorAmount();

                if ($left !== $kept) {
                    $mismatches[] = new InvoiceBalanceMismatch($invoice->order->public_id, $invoice->public_id, $invoice->currency, $left, $kept);
                }
            }
        });

        return $mismatches;
    }
}
