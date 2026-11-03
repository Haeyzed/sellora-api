<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Tenancy\Contracts\StoreTaxDocuments;
use App\Tenant\Orders\Actions\IssueCreditNote;
use App\Tenant\Orders\Actions\IssueInvoice;
use App\Tenant\Orders\Contracts\RefundsAwaitingCreditNotes;
use App\Tenant\Orders\Data\CreditRequest;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Exceptions\CreditNoteExceedsInvoiceException;
use App\Tenant\Orders\Jobs\RenderTaxDocumentPdf;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * The current store's part of the hourly sweep: invoices for sales paid in full, and credit notes for refund parts that reduced a sale, that the queue didn't issue; and PDFs it didn't render, queued again.
 */
final readonly class MissingTaxDocumentSweep implements StoreTaxDocuments
{
    public function __construct(
        private IssueInvoice $issueInvoice,
        private IssueCreditNote $issueCreditNote,
        private RefundsAwaitingCreditNotes $refundsAwaitingCreditNotes,
    ) {}

    public function issueMissing(): int
    {
        $dueBefore = CarbonImmutable::now()->subMinutes(config()->integer('orders.missing_documents_grace_minutes'));

        return $this->issueMissingInvoices($dueBefore) + $this->issueMissingCreditNotes($dueBefore) + $this->queueMissingPdfs($dueBefore);
    }

    /**
     * Documents issued a while ago without a PDF get it queued again (rendering is limited per store, so it may still be waiting).
     */
    private function queueMissingPdfs(CarbonImmutable $dueBefore): int
    {
        $limit = config()->integer('orders.missing_documents_per_run');
        $invoices = Invoice::query()->whereNull('pdf_path')->where('issued_at', '<=', $dueBefore)->orderBy('id')->limit($limit)->pluck('id');
        $creditNotes = CreditNote::query()->whereNull('pdf_path')->where('issued_at', '<=', $dueBefore)->orderBy('id')->limit($limit)->pluck('id');

        foreach ($invoices as $id) {
            RenderTaxDocumentPdf::dispatch(TaxDocumentKind::Invoice, $id);
        }

        foreach ($creditNotes as $id) {
            RenderTaxDocumentPdf::dispatch(TaxDocumentKind::CreditNote, $id);
        }

        return $invoices->count() + $creditNotes->count();
    }

    /**
     * A refund on an order with no invoice to credit is set aside once (it needs no credit note), and the sweep carries on in the same run, so a backlog of them never holds back a credit note that is due. One run checks at most orders.credit_note_checks_per_run refund parts, however long the backlog: the rest wait for the next run, and as each set-aside part is never checked again, the backlog shrinks every run.
     */
    private function issueMissingCreditNotes(CarbonImmutable $dueBefore): int
    {
        $limit = config()->integer('orders.missing_documents_per_run');
        $checksLeft = config()->integer('orders.credit_note_checks_per_run');
        $issued = 0;
        $tried = [];
        // Parts tried and left as they were come back in every list, so each list asks for that many more.
        $stillListed = 0;

        do {
            $setAside = 0;
            // Every list asks for no more new parts than the run may still check, so the cap holds however the loop goes.
            $wanted = min($limit - $issued, $checksLeft);

            foreach ($this->refundsAwaitingCreditNotes->wentBackBefore($dueBefore, $wanted + $stillListed) as $request) {
                if (isset($tried[$request->reference])) {
                    continue;
                }

                $tried[$request->reference] = true;
                $checksLeft--;
                $outcome = $this->issueCreditNoteFor($request);

                if ($outcome === true) {
                    $issued++;
                } elseif ($outcome === false) {
                    $setAside++;
                } else {
                    $stillListed++;
                }

                if ($issued >= $limit) {
                    break;
                }
            }
        } while ($setAside > 0 && $issued < $limit);

        return $issued;
    }

    /**
     * @return bool|null True when its credit note was issued, false when it was set aside as needing none, null when it is left for staff or the next run.
     */
    private function issueCreditNoteFor(CreditRequest $request): ?bool
    {
        try {
            $creditNote = $this->issueCreditNote->handle($request);
        } catch (CreditNoteExceedsInvoiceException $exception) {
            // Staff must look at it; the others still go out.
            report($exception);

            return null;
        }

        if ($creditNote !== null) {
            return true;
        }

        if (! $this->refundsAwaitingCreditNotes->needsNoCreditNote($request->reference)) {
            return null;
        }

        // Only the refund part's public ID: staff find the rest from there.
        Log::info('A refund went back on an order with no invoice to credit, so it gets no credit note.', ['refund_part' => $request->reference]);

        return false;
    }

    private function issueMissingInvoices(CarbonImmutable $dueBefore): int
    {
        $issued = 0;
        $orders = Order::query()
            ->where('payment_status', OrderPaymentStatus::Paid->value)
            ->where('paid_at', '<=', $dueBefore)
            ->whereHas('events', static fn (Builder $events): Builder => $events->where('type', OrderEventType::Paid->value))
            ->whereDoesntHave('invoice')
            ->orderBy('id')
            ->limit(config()->integer('orders.missing_documents_per_run'))
            ->get();

        foreach ($orders as $order) {
            if ($this->issueInvoice->handle($order) !== null) {
                $issued++;
            }
        }

        return $issued;
    }
}
