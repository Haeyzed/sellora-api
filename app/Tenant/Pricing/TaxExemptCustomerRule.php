<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Tenant\Pricing\Contracts\TaxExemptionRule;
use App\Tenant\Pricing\Data\TaxableLine;
use App\Tenant\Pricing\Data\TaxRequest;

/**
 * Exempts every line, shipping included, when staff made the customer tax-exempt (Step 10). Tax calculators never see such lines.
 */
final readonly class TaxExemptCustomerRule implements TaxExemptionRule
{
    /**
     * @return list<string>
     */
    public function exemptLineKeys(TaxRequest $request): array
    {
        if ($request->customer?->isTaxExempt !== true) {
            return [];
        }

        return array_map(static fn (TaxableLine $line): string => $line->key, $request->lines);
    }
}
