<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Pricing\Enums\TaxOutcome;
use InvalidArgumentException;

/**
 * The tax of every line of a request, and who calculated it. The order's tax is the exact sum of its lines' tax.
 */
final readonly class TaxResult
{
    /**
     * @param  array<string, LineTax>  $lines  By line key, in the request's order.
     * @param  string  $calculatedBy  Which calculator worked it out: "rate_table", or a plug-in's feature key.
     *
     * @throws InvalidArgumentException When a line is in another currency or filed under another key.
     */
    public function __construct(
        public string $currency,
        public TaxMode $taxMode,
        public array $lines,
        public string $calculatedBy,
    ) {
        foreach ($lines as $key => $line) {
            if ($line->key !== (string) $key || $line->gross->currency() !== $currency) {
                throw new InvalidArgumentException("Line \"{$key}\" doesn't belong in this result.");
            }
        }
    }

    /**
     * @throws InvalidArgumentException When there is no such line.
     */
    public function line(string $key): LineTax
    {
        return $this->lines[$key] ?? throw new InvalidArgumentException("No line \"{$key}\" in this result.");
    }

    public function totalTax(): Money
    {
        return Money::sum($this->currency, array_map(static fn (LineTax $line): Money => $line->tax, array_values($this->lines)));
    }

    public function totalNet(): Money
    {
        return Money::sum($this->currency, array_map(static fn (LineTax $line): Money => $line->net, array_values($this->lines)));
    }

    public function totalGross(): Money
    {
        return Money::sum($this->currency, array_map(static fn (LineTax $line): Money => $line->gross, array_values($this->lines)));
    }

    /**
     * Whether any line had no rate at its destination, which staff should look at.
     */
    public function hasMissingRates(): bool
    {
        return array_any($this->lines, static fn (LineTax $line): bool => $line->outcome === TaxOutcome::NoRate);
    }
}
