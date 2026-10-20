<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Orders\Documents\TaxDocumentPdf;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

/**
 * Renders a tax document's PDF once and keeps it on the private documents disk. Safe to run twice: a document whose PDF is stored is left alone.
 *
 * The PDF is written before the document records where it is, and the
 * database lets that be recorded only once, so the first render to finish
 * is the one kept.
 */
final readonly class StoreTaxDocumentPdf
{
    public function __construct(
        private TaxDocumentPdf $taxDocumentPdf,
        private FilesystemFactory $filesystems,
    ) {}

    /**
     * Rendering failures propagate, so the queue tries again.
     */
    public function handle(Invoice|CreditNote $document): void
    {
        if ($document->pdf_path !== null) {
            return;
        }

        $path = ($document instanceof Invoice ? 'invoices/' : 'credit-notes/').$document->public_id.'.pdf';
        $this->filesystems->disk(config()->string('orders.documents_disk'))->put($path, $this->taxDocumentPdf->render($document));
        $document->newQuery()->whereKey($document->id)->whereNull('pdf_path')->update(['pdf_path' => $path, 'pdf_rendered_at' => CarbonImmutable::now()]);
    }
}
