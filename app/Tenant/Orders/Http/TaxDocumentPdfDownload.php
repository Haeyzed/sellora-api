<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http;

use App\Tenant\Orders\Exceptions\TaxDocumentPdfNotReadyException;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An invoice's or credit note's PDF as a download named after its number, from the store's private disk, never cached.
 */
final readonly class TaxDocumentPdfDownload
{
    public function __construct(private FilesystemFactory $filesystems) {}

    /**
     * @throws TaxDocumentPdfNotReadyException While it hasn't been rendered yet.
     */
    public function of(Invoice|CreditNote $document): StreamedResponse
    {
        if ($document->pdf_path === null) {
            throw new TaxDocumentPdfNotReadyException;
        }

        return $this->filesystems->disk(config()->string('orders.documents_disk'))->download($document->pdf_path, $document->number.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
