<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pricing;

use App\Shared\Money\Decimal;
use App\Tenant\Pricing\Contracts\TaxCalculator;
use App\Tenant\Pricing\Data\LineTax;
use App\Tenant\Pricing\Data\TaxComponent;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Data\TaxResult;

/**
 * A country plug-in that charges two flat 9% taxes on every line and remembers which lines it was asked about.
 */
final class RecordingTaxPlugIn implements TaxCalculator
{
    public const string FEATURE_KEY = 'test_tax_rules';

    /**
     * @var list<list<string>>
     */
    public static array $askedAbout = [];

    public function calculate(TaxRequest $request): TaxResult
    {
        self::$askedAbout[] = array_map(static fn ($line): string => $line->key, $request->lines);
        $lines = [];

        foreach ($request->lines as $line) {
            $lines[$line->key] = LineTax::atRates($line, $request->taxMode, [
                new TaxComponent('CGST', $request->destination->countryCode, null, Decimal::of(9)),
                new TaxComponent('SGST', $request->destination->countryCode, $request->destination->stateCode, Decimal::of(9)),
            ]);
        }

        return new TaxResult($request->currency, $request->taxMode, $lines, self::FEATURE_KEY);
    }
}
