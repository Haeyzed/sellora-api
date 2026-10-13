<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Contracts;

use App\Tenant\Pricing\Data\TaxRequest;

/**
 * Decides which lines of a request are exempt from tax, before any calculator is asked, so plug-ins never need to know about exemptions.
 *
 * Core applies the exempt tax classes itself, and TaxExemptCustomerRule
 * exempts everything a tax-exempt customer buys. Rules are tagged
 * "pricing.tax_exemption_rules".
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
