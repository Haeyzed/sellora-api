<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Pricing\Contracts\TaxExemptionRule;
use App\Tenant\Pricing\Data\LineTax;
use App\Tenant\Pricing\Data\TaxableLine;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Data\TaxResult;
use App\Tenant\Pricing\Enums\TaxOutcome;
use App\Tenant\Pricing\RateTableTaxCalculator;
use App\Tenant\Pricing\TaxCalculatorRegistry;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Works out the tax of a set of lines: the one way Cart, Checkout and Orders get tax (section 3.3).
 *
 * Exempt lines are settled here first, by their tax class and by every
 * registered exemption rule, so calculators never see them. The rest go to
 * the calculator for the destination country: a plug-in while its feature is
 * Enabled for the store, else the rate table.
 */
final readonly class CalculateTax
{
    public const string EXEMPTION_RULES_TAG = 'pricing.tax_exemption_rules';

    public function __construct(
        private TaxCalculatorRegistry $taxCalculatorRegistry,
        private Container $container,
    ) {}

    /**
     * @throws LogicException When a calculator returns other lines than it was asked about, or in another currency or tax mode.
     */
    public function handle(TaxRequest $request): TaxResult
    {
        $exemptKeys = $this->exemptLineKeys($request);
        $toCalculate = array_values(array_filter($request->lines, static fn (TaxableLine $line): bool => ! isset($exemptKeys[$line->key])));
        $calculated = $toCalculate === [] ? null : $this->taxCalculatorRegistry->for($request->destination->countryCode)->calculate($request->withLines($toCalculate));

        if ($calculated !== null) {
            $this->ensureAnswers($calculated, $request->withLines($toCalculate));
        }

        $lines = [];

        foreach ($request->lines as $line) {
            $lines[$line->key] = $calculated?->lines[$line->key] ?? LineTax::untaxed($line, TaxOutcome::Exempt);
        }

        return new TaxResult($request->currency, $request->taxMode, $lines, $calculated->calculatedBy ?? RateTableTaxCalculator::NAME);
    }

    /**
     * @return array<string, true>
     */
    private function exemptLineKeys(TaxRequest $request): array
    {
        $exempt = [];

        foreach ($request->lines as $line) {
            if ($line->taxClass->isExempt) {
                $exempt[$line->key] = true;
            }
        }

        /** @var iterable<TaxExemptionRule> $rules */
        $rules = $this->container->tagged(self::EXEMPTION_RULES_TAG);

        foreach ($rules as $rule) {
            foreach ($rule->exemptLineKeys($request) as $key) {
                $exempt[$key] = true;
            }
        }

        return $exempt;
    }

    private function ensureAnswers(TaxResult $result, TaxRequest $asked): void
    {
        $askedKeys = array_map(static fn (TaxableLine $line): string => $line->key, $asked->lines);
        $answeredKeys = array_map('strval', array_keys($result->lines));
        sort($askedKeys);
        sort($answeredKeys);

        if ($result->currency !== $asked->currency || $result->taxMode !== $asked->taxMode || $answeredKeys !== $askedKeys) {
            throw new LogicException("The tax calculator \"{$result->calculatedBy}\" answered for other lines than it was asked about.");
        }
    }
}
