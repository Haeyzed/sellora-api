<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pricing;

use App\Tenant\Pricing\Contracts\TaxCalculator;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Data\TaxResult;

/**
 * A broken plug-in that answers about no lines at all.
 */
final class ForgetfulTaxPlugIn implements TaxCalculator
{
    public function calculate(TaxRequest $request): TaxResult
    {
        return new TaxResult($request->currency, $request->taxMode, [], 'forgetful');
    }
}
