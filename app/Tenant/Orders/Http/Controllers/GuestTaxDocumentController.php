<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Exceptions\TaxDocumentPdfNotReadyException;
use App\Tenant\Orders\Http\Requests\GuestOrderRequest;
use App\Tenant\Orders\Http\Resources\CreditNoteResource;
use App\Tenant\Orders\Http\Resources\InvoiceResource;
use App\Tenant\Orders\Http\TaxDocumentPdfDownload;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A guest's invoice and credit notes for their order, reached with its access token in the X-Order-Token header. Without it they are not found.
 */
final class GuestTaxDocumentController extends Controller
{
    /**
     * Show your order's invoice.
     *
     * Issued once the order is paid in full; until then, not found. Lists the
     * credit notes against it.
     */
    public function invoice(GuestOrderRequest $request, Order $order): InvoiceResource
    {
        return new InvoiceResource($this->invoiceOf($request, $order)->load(['order', 'lines.taxes', 'creditNotes']));
    }

    /**
     * Download your order's invoice.
     *
     * A PDF named after its number. Until it has been rendered, a moment after
     * the invoice is issued, this answers 409 tax_document_pdf_not_ready.
     *
     * @throws TaxDocumentPdfNotReadyException
     */
    public function invoicePdf(GuestOrderRequest $request, Order $order, TaxDocumentPdfDownload $download): StreamedResponse
    {
        return $download->of($this->invoiceOf($request, $order));
    }

    /**
     * Show one of your order's credit notes.
     */
    public function creditNote(GuestOrderRequest $request, Order $order, string $creditNote): CreditNoteResource
    {
        return new CreditNoteResource($this->creditNoteOf($request, $order, $creditNote)->load(['order', 'invoice', 'lines.taxes']));
    }

    /**
     * Download one of your order's credit notes.
     *
     * A PDF named after its number. Until it has been rendered, this answers
     * 409 tax_document_pdf_not_ready.
     *
     * @throws TaxDocumentPdfNotReadyException
     */
    public function creditNotePdf(GuestOrderRequest $request, Order $order, string $creditNote, TaxDocumentPdfDownload $download): StreamedResponse
    {
        return $download->of($this->creditNoteOf($request, $order, $creditNote));
    }

    /**
     * @throws ModelNotFoundException When the token isn't this order's or it has no invoice yet.
     */
    private function invoiceOf(GuestOrderRequest $request, Order $order): Invoice
    {
        return Invoice::query()->where('order_id', $request->guestOrder($order)->id)->firstOrFail();
    }

    /**
     * @throws ModelNotFoundException When the token isn't this order's or the credit note isn't for it.
     */
    private function creditNoteOf(GuestOrderRequest $request, Order $order, string $creditNote): CreditNote
    {
        return CreditNote::query()->where('order_id', $request->guestOrder($order)->id)->where('public_id', $creditNote)->firstOrFail();
    }
}
