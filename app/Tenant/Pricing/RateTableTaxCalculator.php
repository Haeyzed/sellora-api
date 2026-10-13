<?php

declare(strict_types=1);

namespace App\Tenant\Pricing;

use App\Shared\Money\Decimal;
use App\Tenant\Pricing\Contracts\TaxCalculator;
use App\Tenant\Pricing\Data\LineTax;
use App\Tenant\Pricing\Data\TaxComponent;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Data\TaxResult;
use App\Tenant\Pricing\Enums\TaxOutcome;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Core's tax calculator: the store's own rates, per tax class and region (section 3.3).
 *
 * The most specific rate wins: the destination state's rate for the line's
 * class, else the country-wide rate; they never add up. With neither, the
 * line has no tax and is marked "no rate". One query for the whole request.
 * Plug-ins may call it and refine its result.
 */
final readonly class RateTableTaxCalculator implements TaxCalculator
{
    public const string NAME = 'rate_table';

    public function calculate(TaxRequest $request): TaxResult
    {
        $rates = $this->ratesByClass($request);
        $lines = [];

        foreach ($request->lines as $line) {
            $rate = $rates[$line->taxClass->id] ?? null;

            $lines[$line->key] = $line->taxClass->isExempt
                ? LineTax::untaxed($line, TaxOutcome::Exempt)
                : ($rate === null
                    ? LineTax::untaxed($line, TaxOutcome::NoRate)
                    : LineTax::atRates($line, $request->taxMode, [new TaxComponent($rate->name, $rate->country_code, $rate->state_code, Decimal::of($rate->rate))]));
        }

        return new TaxResult($request->currency, $request->taxMode, $lines, self::NAME);
    }

    /**
     * The rate that applies to each class at the destination, by the class's public ID.
     *
     * @return array<string, stdClass>
     */
    private function ratesByClass(TaxRequest $request): array
    {
        $classIds = array_values(array_unique(array_map(static fn ($line): string => $line->taxClass->id, $request->lines)));

        if ($classIds === []) {
            return [];
        }

        $destination = $request->destination;
        $rows = DB::table('tax_rates')
            ->join('tax_classes', 'tax_classes.id', '=', 'tax_rates.tax_class_id')
            ->whereIn('tax_classes.public_id', $classIds)
            ->where('tax_rates.country_code', $destination->countryCode)
            ->where(static function ($query) use ($destination): void {
                $query->whereNull('tax_rates.state_code');

                if ($destination->stateCode !== null) {
                    $query->orWhere('tax_rates.state_code', $destination->stateCode);
                }
            })
            // Country-wide first, so a state's rate overwrites it below.
            ->orderByRaw('tax_rates.state_code is not null')
            ->get(['tax_classes.public_id as class_id', 'tax_rates.name', 'tax_rates.country_code', 'tax_rates.state_code', 'tax_rates.rate']);

        $rates = [];

        foreach ($rows as $row) {
            $rates[(string) $row->class_id] = $row;
        }

        return $rates;
    }
}
