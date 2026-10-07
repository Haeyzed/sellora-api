<?php

declare(strict_types=1);

namespace App\Shared\Money\Contracts;

/**
 * One domain's answer to "does the current store hold anything priced?", such as products, price lists or orders.
 *
 * Stored prices are read through the store's base currency and tax mode, so
 * both are locked once anything is priced (section 3.3). Every domain or
 * module that stores prices registers an implementation in the
 * PricedRecordsRegistry.
 */
interface PricedRecords
{
    /**
     * Whether this domain holds at least one priced record in the current store.
     */
    public function exist(): bool;
}
