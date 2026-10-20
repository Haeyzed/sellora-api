<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Tenant\Orders\Models\Invoice;
use RuntimeException;

/**
 * A credit note would credit more than is left on its invoice. Refunds that reduce the sale never exceed the order's total, so this means records disagree: staff must look at it.
 */
final class CreditNoteExceedsInvoiceException extends RuntimeException
{
    public static function for(Invoice $invoice, string $reference): self
    {
        // Public IDs only: staff find the rest from there.
        return new self("Crediting refund part {$reference} would take invoice {$invoice->public_id} below zero.");
    }
}
