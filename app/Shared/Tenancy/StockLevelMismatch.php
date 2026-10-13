<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

/**
 * A stock level that doesn't match the sum of its movements, or whose reserved count doesn't match its active reservations, found by reconciling a store's stock ledger.
 */
final readonly class StockLevelMismatch
{
    public function __construct(
        public string $variantId,
        public string $locationId,
        public int $onHand,
        public int $onHandFromLedger,
        public int $reserved,
        public int $reservedFromLedger,
        public int $reservedByReservations,
    ) {}
}
