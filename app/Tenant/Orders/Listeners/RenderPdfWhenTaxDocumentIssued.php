<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Listeners;

use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Events\CreditNoteIssued;
use App\Tenant\Orders\Events\InvoiceIssued;
use App\Tenant\Orders\Jobs\RenderTaxDocumentPdf;

/**
 * Queues the PDF of an invoice or credit note once it is issued (both are announced only once saved).
 */
final readonly class RenderPdfWhenTaxDocumentIssued
{
    public function handle(InvoiceIssued|CreditNoteIssued $event): void
    {
        $event instanceof InvoiceIssued
            ? RenderTaxDocumentPdf::dispatch(TaxDocumentKind::Invoice, $event->invoice->id)
            : RenderTaxDocumentPdf::dispatch(TaxDocumentKind::CreditNote, $event->creditNote->id);
    }
}
