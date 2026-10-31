<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

/**
 * An invoice whose total less its credit notes doesn't equal the sale money the store has kept for its order, found by reconciling a store's invoices. Amounts are in minor units; only public IDs, no buyer's details.
 */
final readonly class InvoiceBalanceMismatch
{
    public function __construct(
        public string $orderId,
        public string $invoiceId,
        public string $currency,
        public int $invoiceLeft,
        public int $saleKept,
    ) {}
}
