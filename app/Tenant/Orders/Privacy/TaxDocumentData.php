<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Privacy;

use App\Shared\Privacy\Contracts\KeepsPersonalDataByLaw;
use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Services\TaxDocumentBuyerErasure;
use App\Tenant\Orders\Services\TaxRecordRetention;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * A customer's or guest's invoices and credit notes: exported as issued, buyer's details included; on erasure, kept as they are until the store's tax-record retention ends, and cleared of the buyer's details at once for those already past it.
 *
 * A customer's are those of their orders; a guest's, those of guest orders
 * issued to their email, which the document keeps even after its order is
 * anonymised. The daily purge (TaxDocumentRetention) clears the rest once
 * their retention ends.
 */
final readonly class TaxDocumentData implements KeepsPersonalDataByLaw, PersonalDataHandler
{
    public function __construct(
        private TaxRecordRetention $taxRecordRetention,
        private TaxDocumentBuyerErasure $taxDocumentBuyerErasure,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $invoices = $this->documents(Invoice::query(), $subject)->orderBy('id')->get()->map(static fn (Invoice $invoice): array => [
            'type' => 'invoice',
            'number' => $invoice->number,
            'issued_on' => $invoice->issued_on->toDateString(),
            'total' => $invoice->total->format('en'),
            ...self::buyer($invoice),
        ]);
        $creditNotes = $this->documents(CreditNote::query()->with('invoice'), $subject)->orderBy('id')->get()->map(static fn (CreditNote $creditNote): array => [
            'type' => 'credit_note',
            'number' => $creditNote->number,
            'invoice' => $creditNote->invoice->number,
            'issued_on' => $creditNote->issued_on->toDateString(),
            'total' => $creditNote->total->format('en'),
            ...self::buyer($creditNote),
        ]);

        return [...array_values($invoices->all()), ...array_values($creditNotes->all())];
    }

    public function erase(DataSubject $subject): void
    {
        $this->taxDocumentBuyerErasure->clearEnded($this->documents(Invoice::query(), $subject), $this->documents(CreditNote::query(), $subject));
    }

    public function keptUntil(DataSubject $subject): ?CarbonImmutable
    {
        $issuedFrom = $this->taxRecordRetention->endedForIssuedBefore()->toDateString();
        $latest = collect([
            $this->documents(Invoice::query(), $subject)->whereNull('buyer_erased_at')->where('issued_on', '>=', $issuedFrom)->max('issued_on'),
            $this->documents(CreditNote::query(), $subject)->whereNull('buyer_erased_at')->where('issued_on', '>=', $issuedFrom)->max('issued_on'),
        ])->filter(static fn (mixed $date): bool => is_string($date))->max();

        return is_string($latest) ? $this->taxRecordRetention->keptUntil(CarbonImmutable::parse($latest)) : null;
    }

    /**
     * The subject's documents: their orders' as a customer, or as a guest those of guest orders issued to their email.
     *
     * @template TDocument of Invoice|CreditNote
     *
     * @param  Builder<TDocument>  $documents
     * @return Builder<TDocument>
     */
    private function documents(Builder $documents, DataSubject $subject): Builder
    {
        if ($subject->type === GuestDataSubject::TYPE) {
            return $documents->where('buyer_email', $subject->publicId)->whereHas('order', static fn (Builder $orders) => $orders->whereNull('customer_id'));
        }

        $customer = CustomerDataSubject::customer($subject);

        return $customer === null
            ? $documents->whereRaw('false')
            : $documents->whereHas('order', static fn (Builder $orders) => $orders->where('customer_id', $customer->id));
    }

    /**
     * @return array{buyer: array<string, mixed>|null}
     */
    private static function buyer(Invoice|CreditNote $document): array
    {
        return ['buyer' => $document->buyer_erased_at !== null ? null : [
            'name' => $document->buyer_name,
            'email' => $document->buyer_email,
            'phone' => $document->buyer_phone,
            'tax_id' => $document->buyer_tax_id === null ? null : ['id' => $document->buyer_tax_id, 'country' => $document->buyer_tax_id_country],
            'billing_address' => $document->billing_address,
            'shipping_address' => $document->shipping_address,
        ]];
    }
}
