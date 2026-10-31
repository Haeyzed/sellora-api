<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\CreditNoteLine;
use App\Tenant\Orders\Models\CreditNoteTax;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceLine;
use App\Tenant\Orders\Models\InvoiceTax;
use LogicException;

/**
 * What is left to credit on each line of an invoice, in money and in tax by rate, worked out from the credit notes issued so far. Every credit takes its tax from here, so credited tax can never pass what a line or a rate was charged.
 *
 * A credit's tax is its share of the tax still left on the line, and the
 * credit that finishes a line takes exactly what is left of it. A price
 * reduction belongs to no line, so it is spread across what is left of all of
 * them by largest remainder; that way the lines always add up to what is left
 * on the invoice. A line can be credited for more than is left of it when a
 * price reduction came first: it then takes all its tax, and the excess comes
 * off what is left of the other lines.
 */
final class InvoiceCreditsLeft
{
    /** @var array<int, InvoiceLine> The invoice's lines in order, by ID. */
    private array $lines = [];

    /** @var array<int, int> Money left, by line ID. */
    private array $amounts = [];

    /** @var array<int, array<string, int>> Tax left by rate, by line ID. */
    private array $taxes = [];

    /** @var array<int, array<string, InvoiceTax>> The tax rows, by line ID and rate. */
    private array $rates = [];

    private function __construct(private readonly string $currency) {}

    public static function of(Invoice $invoice): self
    {
        $left = new self($invoice->currency);

        foreach ($invoice->lines->sortBy('position') as $line) {
            $left->lines[$line->id] = $line;
            $left->amounts[$line->id] = $line->total->minorAmount();
            $left->taxes[$line->id] = [];
            $left->rates[$line->id] = [];

            foreach ($line->taxes as $tax) {
                $key = self::rate($tax);
                $left->taxes[$line->id][$key] = ($left->taxes[$line->id][$key] ?? 0) + $tax->tax->minorAmount();
                $left->rates[$line->id][$key] = $tax;
            }
        }

        foreach (CreditNote::query()->where('invoice_id', $invoice->id)->with('lines.taxes')->orderBy('id')->get() as $creditNote) {
            foreach ($creditNote->lines->sortBy('position') as $creditedLine) {
                $left->replay($creditedLine);
            }
        }

        return $left;
    }

    /**
     * An amount shared across what is left of the lines by largest remainder, without taking it: the line and its share, in order, leaving out lines with no share.
     *
     * @return list<array{InvoiceLine, Money}>
     */
    public function shares(Money $amount): array
    {
        $shares = [];

        foreach ($this->allocated($amount) as $lineId => $share) {
            if ($share->isPositive()) {
                $shares[] = [$this->lines[$lineId], $share];
            }
        }

        return $shares;
    }

    /**
     * Takes an amount off a line.
     *
     * @return list<array{InvoiceTax, Money}> The tax it carries, by rate.
     */
    public function credit(InvoiceLine $line, Money $amount): array
    {
        return $this->withRates($line->id, $this->take($line->id, $amount->minorAmount()));
    }

    /**
     * Takes a price reduction off what is left of all the lines.
     *
     * @return list<array{InvoiceTax, Money}> The tax it carries, by rate, once for each rate.
     */
    public function creditPriceReduction(Money $amount): array
    {
        /** @var array<string, array{InvoiceTax, int}> $byRate */
        $byRate = [];

        foreach ($this->allocated($amount) as $lineId => $share) {
            foreach ($this->take($lineId, $share->minorAmount()) as $key => $tax) {
                $byRate[$key] = [$byRate[$key][0] ?? $this->rates[$lineId][$key], ($byRate[$key][1] ?? 0) + $tax];
            }
        }

        $taxes = [];

        foreach ($byRate as [$invoiceTax, $tax]) {
            $taxes[] = [$invoiceTax, Money::ofMinor($tax, $this->currency)];
        }

        return $taxes;
    }

    /**
     * @return array<int, Money> By line ID.
     */
    private function allocated(Money $amount): array
    {
        if ($this->amounts === []) {
            throw new LogicException('An invoice with money left on it has lines.');
        }

        return $amount->allocate($this->amounts);
    }

    /**
     * Takes an amount off a line, with its tax, and returns the tax taken by rate.
     *
     * @return array<string, int>
     */
    private function take(int $lineId, int $amount): array
    {
        $left = $this->amounts[$lineId];
        $taxLeft = array_sum($this->taxes[$lineId]);
        $tax = $amount >= $left ? $taxLeft : $this->shareOf($taxLeft, $amount, $left);
        // Tax is part of the amount: a credit never carries more of it than it comes to.
        $tax = min($tax, $amount);
        $byRate = $this->byRate($lineId, $tax);

        foreach ($byRate as $key => $part) {
            $this->taxes[$lineId][$key] -= $part;
        }

        $this->amounts[$lineId] = max(0, $left - $amount);
        $this->takeFromTheOthers($lineId, $amount - $left);

        return $byRate;
    }

    /**
     * Tax split across a line's rates by what is left at each, so no rate can go below zero.
     *
     * @return array<string, int>
     */
    private function byRate(int $lineId, int $tax): array
    {
        $weights = array_filter($this->taxes[$lineId], static fn (int $left): bool => $left > 0);

        if ($tax === 0 || $weights === []) {
            return [];
        }

        return array_map(static fn (Money $part): int => $part->minorAmount(), Money::ofMinor($tax, $this->currency)->allocate($weights));
    }

    /**
     * What a line was credited over what was left of it comes off the other lines, so all of them still add up to what is left on the invoice.
     */
    private function takeFromTheOthers(int $lineId, int $excess): void
    {
        $others = array_filter($this->amounts, static fn (int $left, int $id): bool => $id !== $lineId && $left > 0, ARRAY_FILTER_USE_BOTH);

        if ($excess <= 0 || $others === []) {
            return;
        }

        $parts = Money::ofMinor(min($excess, array_sum($others)), $this->currency)->allocate($others);

        foreach ($parts as $id => $part) {
            $this->amounts[$id] -= $part->minorAmount();
        }
    }

    /**
     * Takes off what a credit note already issued took. Its tax is what it says, so the record stands even where it was worked out another way.
     */
    private function replay(CreditNoteLine $creditedLine): void
    {
        if ($creditedLine->invoice_line_id === null) {
            $this->replayPriceReduction($creditedLine);

            return;
        }

        $lineId = $creditedLine->invoice_line_id;
        $amount = $creditedLine->total->minorAmount();
        $left = $this->amounts[$lineId];
        $this->amounts[$lineId] = max(0, $left - $amount);
        $this->takeFromTheOthers($lineId, $amount - $left);

        foreach ($creditedLine->taxes as $tax) {
            $key = self::rate($tax);
            $this->taxes[$lineId][$key] = max(0, ($this->taxes[$lineId][$key] ?? 0) - $tax->tax->minorAmount());
        }
    }

    private function replayPriceReduction(CreditNoteLine $creditedLine): void
    {
        foreach ($this->allocated($creditedLine->total) as $lineId => $share) {
            $this->amounts[$lineId] -= min($this->amounts[$lineId], $share->minorAmount());
        }

        foreach ($creditedLine->taxes as $tax) {
            $key = self::rate($tax);
            $weights = [];

            foreach ($this->taxes as $lineId => $left) {
                if (($left[$key] ?? 0) > 0) {
                    $weights[$lineId] = $left[$key];
                }
            }

            if ($weights === [] || ! $tax->tax->isPositive()) {
                continue;
            }

            foreach ($tax->tax->allocate($weights) as $lineId => $part) {
                $this->taxes[$lineId][$key] = max(0, $this->taxes[$lineId][$key] - $part->minorAmount());
            }
        }
    }

    /**
     * @param  array<string, int>  $byRate
     * @return list<array{InvoiceTax, Money}>
     */
    private function withRates(int $lineId, array $byRate): array
    {
        $taxes = [];

        foreach ($byRate as $key => $tax) {
            $taxes[] = [$this->rates[$lineId][$key], Money::ofMinor($tax, $this->currency)];
        }

        return $taxes;
    }

    /**
     * A share of a whole that a part is of another whole, by largest remainder.
     */
    private function shareOf(int $whole, int $part, int $of): int
    {
        if ($whole === 0 || $part === 0) {
            return 0;
        }

        return Money::ofMinor($whole, $this->currency)->allocate([$part, $of - $part])[0]->minorAmount();
    }

    private static function rate(InvoiceTax|CreditNoteTax $tax): string
    {
        return implode('|', [$tax->name, $tax->country_code, $tax->state_code, $tax->rate_percent->toString()]);
    }
}
