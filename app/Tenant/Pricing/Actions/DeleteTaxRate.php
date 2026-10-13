<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Pricing\Models\TaxRate;

/**
 * Removes a rate for good. Audited. Its class then has no rate in that region (or falls back to the country-wide rate), and orders already placed keep what they were charged.
 */
final readonly class DeleteTaxRate
{
    public function handle(TaxRate $taxRate): void
    {
        $taxRate->delete();
    }
}
