<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Contracts;

use App\Tenant\Orders\Data\CreditRequest;
use Carbon\CarbonImmutable;

/**
 * Refund parts that reduced a sale and have gone back but have no credit note yet, for the hourly sweep. Implemented by Payments.
 */
interface RefundsAwaitingCreditNotes
{
    /**
     * @return list<CreditRequest> Oldest first, those that went back before the time given.
     */
    public function wentBackBefore(CarbonImmutable $time, int $limit): array;
}
