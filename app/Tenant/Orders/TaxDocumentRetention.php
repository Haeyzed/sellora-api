<?php

declare(strict_types=1);

namespace App\Tenant\Orders;

use App\Shared\Privacy\PersonalDataErasure;
use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Services\TaxDocumentBuyerErasure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clears the buyer's details from invoices and credit notes in every store's daily purge once both are true: their order has been anonymised (its buyer erased, or its own retention passed), and the store's tax-record retention for them has ended.
 *
 * Until then the law makes the store keep them as issued, even after the
 * buyer asked to be erased. The rest of each document stays for good.
 */
final readonly class TaxDocumentRetention implements RetentionPolicy
{
    public function __construct(
        private PersonalDataErasure $personalDataErasure,
        private TaxDocumentBuyerErasure $taxDocumentBuyerErasure,
    ) {}

    public function periodKey(): string
    {
        return 'tax_document_buyers';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $anonymised = static fn (Builder $orders) => $orders->whereNotNull('anonymised_at');
        $invoices = Invoice::query()->whereHas('order', $anonymised)->where('issued_at', '<', $cutoff);
        $creditNotes = CreditNote::query()->whereHas('order', $anonymised)->where('issued_at', '<', $cutoff);

        return $this->personalDataErasure->run(fn (): int => $this->taxDocumentBuyerErasure->clearEnded($invoices, $creditNotes));
    }
}
