<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Contracts;

use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Data\TaxResult;

/**
 * Works out the tax of a set of lines. Core has the rate table; country plug-ins register their own for their destination countries (section 3.3).
 *
 * A calculator reads nothing but its request, returns one LineTax per line,
 * and builds each with LineTax::atRates() or LineTax::untaxed() so the
 * rounding rule is the same everywhere. It is never asked about exempt lines:
 * core settles those first.
 */
interface TaxCalculator
{
    public function calculate(TaxRequest $request): TaxResult;
}
