<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Events;

use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The scheduled stock check found stock levels in a store that don't match its stock movement ledger: a bug or a hand-edited database to investigate.
 */
final readonly class StockLevelsDoNotMatchLedger
{
    use Dispatchable;

    public function __construct(
        public Tenant $store,
        public int $mismatches,
    ) {}
}
