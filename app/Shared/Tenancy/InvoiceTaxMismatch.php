<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use App\Shared\Tenancy\Enums\InvoiceTaxProblem;

/**
 * Tax on an invoice's credit notes that doesn't fit the invoice, found by reconciling a store's invoices: more credited than was invoiced on a line or at a rate, or tax still left at a rate once the invoice is credited in full. Amounts are in minor units; only public IDs and the rate, no buyer's details.
 */
final readonly class InvoiceTaxMismatch
{
    /**
     * @param  int|null  $linePosition  The invoice line's position, from 0; null when it is about the rate across the whole invoice.
     * @param  string  $rate  The rate's name and percent, with its country and state.
     */
    public function __construct(
        public string $orderId,
        public string $invoiceId,
        public string $currency,
        public InvoiceTaxProblem $problem,
        public ?int $linePosition,
        public string $rate,
        public int $credited,
        public int $invoiced,
    ) {}
}
