<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Contracts;

use App\Tenant\Orders\Data\CreditRequest;
use Carbon\CarbonImmutable;

/**
 * Refund parts that reduced a sale and have gone back but have no credit note yet, for the hourly sweep, leaving out those it found need none. Implemented by Payments.
 */
interface RefundsAwaitingCreditNotes
{
    /**
     * @return list<CreditRequest> Oldest first, those that went back before the time given.
     */
    public function wentBackBefore(CarbonImmutable $time, int $limit): array;

    /**
     * Records that a refund part needs no credit note, as its order has no invoice to credit, so it is never returned again.
     *
     * @param  string  $reference  The refund part's public ID (CreditRequest::$reference).
     * @return bool Whether it was recorded now; false when it already was.
     */
    public function needsNoCreditNote(string $reference): bool;
}
