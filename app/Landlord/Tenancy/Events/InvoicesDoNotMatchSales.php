<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Events;

use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The scheduled invoice check found invoices in a store whose credit notes don't add up with the sale money kept, or with the tax invoiced: a bug or a hand-edited database to investigate.
 */
final readonly class InvoicesDoNotMatchSales
{
    use Dispatchable;

    public function __construct(
        public Tenant $store,
        public int $mismatches,
    ) {}
}
