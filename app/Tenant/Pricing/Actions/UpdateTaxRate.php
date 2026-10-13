<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Shared\Money\Decimal;
use App\Tenant\Pricing\Models\TaxRate;

/**
 * Changes a rate's name or percentage. Audited. Its class and region stay: for another region, add a rate there.
 *
 * Orders already placed keep the rate they were charged (section 3.3).
 */
final readonly class UpdateTaxRate
{
    public function handle(TaxRate $taxRate, ?string $name, ?Decimal $rate): TaxRate
    {
        if ($name !== null) {
            $taxRate->name = $name;
        }

        if ($rate !== null) {
            $taxRate->rate = $rate;
        }

        $taxRate->save();

        return $taxRate;
    }
}
