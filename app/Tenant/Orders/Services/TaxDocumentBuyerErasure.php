<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clears the buyer's details from invoices and credit notes whose tax-record retention has ended, inside a PersonalDataErasure run (the database refuses it otherwise); the rest of each document stays.
 *
 * Documents still within their retention keep the buyer's details: the law
 * makes the store keep them as issued.
 */
final readonly class TaxDocumentBuyerErasure
{
    private const array BUYER = ['buyer_name' => null, 'buyer_email' => null, 'buyer_phone' => null, 'buyer_tax_id' => null, 'buyer_tax_id_country' => null, 'billing_address' => null, 'shipping_address' => null];

    public function __construct(private TaxRecordRetention $taxRecordRetention) {}

    /**
     * @param  Builder<Invoice>  $invoices
     * @param  Builder<CreditNote>  $creditNotes
     * @return int How many documents were cleared.
     */
    public function clearEnded(Builder $invoices, Builder $creditNotes): int
    {
        $issuedBefore = $this->taxRecordRetention->endedForIssuedBefore()->toDateString();
        $cleared = ['buyer_erased_at' => CarbonImmutable::now(), ...self::BUYER];

        return $invoices->whereNull('buyer_erased_at')->where('issued_on', '<', $issuedBefore)->update($cleared)
            + $creditNotes->whereNull('buyer_erased_at')->where('issued_on', '<', $issuedBefore)->update($cleared);
    }
}
