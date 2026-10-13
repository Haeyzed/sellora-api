<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Contracts;

use App\Tenant\Pricing\Data\TaxRequest;

/**
 * Decides which lines of a request are exempt from tax, before any calculator is asked, so plug-ins never need to know about exemptions.
 *
 * Core applies the exempt tax classes itself. Customers (Step 10) will add a
 * rule for tax-exempt customers, tagged "pricing.tax_exemption_rules";
 * nothing else changes.
 */
interface TaxExemptionRule
{
    /**
     * The keys of the lines this rule exempts.
     *
     * @return list<string>
     */
    public function exemptLineKeys(TaxRequest $request): array;
}
