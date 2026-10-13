<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pricing;

use App\Tenant\Pricing\Contracts\TaxExemptionRule;
use App\Tenant\Pricing\Data\TaxRequest;

/**
 * An exemption rule, as Customers will add for tax-exempt customers, that exempts the lines whose keys start with "exempt-".
 */
final class ExemptLinesNamed implements TaxExemptionRule
{
    public function exemptLineKeys(TaxRequest $request): array
    {
        $keys = [];

        foreach ($request->lines as $line) {
            if (str_starts_with($line->key, 'exempt-')) {
                $keys[] = $line->key;
            }
        }

        return $keys;
    }
}
