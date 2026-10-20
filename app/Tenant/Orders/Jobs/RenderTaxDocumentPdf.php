<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Jobs;

use App\Tenant\Orders\Actions\StoreTaxDocumentPdf;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Renders a tax document's PDF on the default queue, in its store, limited per store so one store's burst never holds up another's. Safe to run twice; the hourly sweep queues any still missing.
 */
final class RenderTaxDocumentPdf implements ShouldQueue
{
    use Queueable;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly TaxDocumentKind $kind,
        public readonly int $documentId,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('tax-document-pdfs')];
    }

    /**
     * Waiting for the store's limit releases it, so it keeps trying for a while; the hourly sweep queues it again after that.
     */
    public function retryUntil(): DateTimeInterface
    {
        return CarbonImmutable::now()->addHours(6);
    }

    public function handle(StoreTaxDocumentPdf $storeTaxDocumentPdf): void
    {
        $document = $this->kind === TaxDocumentKind::Invoice
            ? Invoice::query()->find($this->documentId)
            : CreditNote::query()->find($this->documentId);

        if ($document !== null) {
            $storeTaxDocumentPdf->handle($document);
        }
    }
}
