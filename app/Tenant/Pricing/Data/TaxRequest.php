<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\TaxMode;
use InvalidArgumentException;

/**
 * Everything a tax calculator needs, passed in rather than read from settings, so a result can be tested and saved exactly as it was calculated (section 3.3).
 *
 * Information added later (such as the customer's tax exemption in Step 10)
 * arrives as new optional properties with defaults: calculators only read
 * what they use, so neither the TaxCalculator contract nor any plug-in
 * changes. Exemptions themselves are applied by core before a calculator is
 * asked (TaxExemptionRule), so a plug-in never has to know about them.
 */
final readonly class TaxRequest
{
    /**
     * @param  string  $currency  ISO 4217; every line is in it.
     * @param  TaxMode  $taxMode  Whether line amounts already include tax.
     * @param  TaxAddress  $origin  Where the store sells from: its address.
     * @param  TaxAddress  $destination  Where the order goes: the shipping address, or the store's country before one is known.
     * @param  list<TaxableLine>  $lines
     *
     * @throws InvalidArgumentException When a line is in another currency, or two lines share a key.
     */
    public function __construct(
        public string $currency,
        public TaxMode $taxMode,
        public TaxAddress $origin,
        public TaxAddress $destination,
        public array $lines,
    ) {
        $keys = [];

        foreach ($lines as $line) {
            if ($line->amount->currency() !== $currency) {
                throw new InvalidArgumentException("Line \"{$line->key}\" is in {$line->amount->currency()}, not {$currency}.");
            }

            if (isset($keys[$line->key])) {
                throw new InvalidArgumentException("Two lines share the key \"{$line->key}\".");
            }

            $keys[$line->key] = true;
        }
    }

    /**
     * The same request with only some of its lines, such as those a calculator is asked about.
     *
     * @param  list<TaxableLine>  $lines
     */
    public function withLines(array $lines): self
    {
        return new self($this->currency, $this->taxMode, $this->origin, $this->destination, $lines);
    }
}
