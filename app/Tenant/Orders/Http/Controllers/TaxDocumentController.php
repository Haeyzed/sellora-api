<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Exceptions\TaxDocumentPdfNotReadyException;
use App\Tenant\Orders\Http\Requests\ListTaxDocumentsRequest;
use App\Tenant\Orders\Http\Requests\ViewInvoicesRequest;
use App\Tenant\Orders\Http\Resources\CreditNoteResource;
use App\Tenant\Orders\Http\Resources\InvoiceResource;
use App\Tenant\Orders\Http\TaxDocumentPdfDownload;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff looking at the store's invoices and credit notes, and downloading their PDFs. All need the invoices.view permission.
 */
final class TaxDocumentController extends Controller
{
    /** What showing an invoice needs, loaded at once. */
    private const array INVOICE = ['order', 'lines.taxes', 'creditNotes'];

    /** What showing a credit note needs, loaded at once. */
    private const array CREDIT_NOTE = ['order', 'invoice', 'lines.taxes'];

    /**
     * List invoices.
     *
     * Newest first.
     */
    public function invoices(ListTaxDocumentsRequest $request): AnonymousResourceCollection
    {
        $query = Invoice::query()->with(self::INVOICE);
        $request->applyFilters($query);

        return InvoiceResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show an invoice.
     */
    public function invoice(ViewInvoicesRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(self::INVOICE));
    }

    /**
     * Download an invoice's PDF.
     *
     * Named after its number. Until it has been rendered, a moment after the
     * invoice is issued, this answers 409 tax_document_pdf_not_ready.
     *
     * @throws TaxDocumentPdfNotReadyException
     */
    public function invoicePdf(ViewInvoicesRequest $request, Invoice $invoice, TaxDocumentPdfDownload $download): StreamedResponse
    {
        return $download->of($invoice);
    }

    /**
     * List credit notes.
     *
     * Newest first.
     */
    public function creditNotes(ListTaxDocumentsRequest $request): AnonymousResourceCollection
    {
        $query = CreditNote::query()->with(self::CREDIT_NOTE);
        $request->applyFilters($query);

        return CreditNoteResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show a credit note.
     */
    public function creditNote(ViewInvoicesRequest $request, CreditNote $creditNote): CreditNoteResource
    {
        return new CreditNoteResource($creditNote->load(self::CREDIT_NOTE));
    }

    /**
     * Download a credit note's PDF.
     *
     * Named after its number. Until it has been rendered, this answers 409
     * tax_document_pdf_not_ready.
     *
     * @throws TaxDocumentPdfNotReadyException
     */
    public function creditNotePdf(ViewInvoicesRequest $request, CreditNote $creditNote, TaxDocumentPdfDownload $download): StreamedResponse
    {
        return $download->of($creditNote);
    }
}
