<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Shared\Tenancy\Enums\InvoiceTaxProblem;
use App\Shared\Tenancy\InvoiceTaxMismatch;
use App\Tenant\Orders\Models\CreditNoteTax;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\InvoiceTax;

/**
 * Checks the tax an invoice's credit notes carry against the invoice's own, from the stored rows alone: no line or rate may have more credited than was invoiced, and an invoice credited in full has none left at any rate. A price reduction belongs to no line, so its tax counts toward the rates only.
 */
final readonly class InvoiceTaxCheck
{
    /**
     * @param  Invoice  $invoice  With its lines' taxes and its credit notes' lines' taxes loaded.
     * @return list<InvoiceTaxMismatch>
     */
    public function problems(Invoice $invoice): array
    {
        /** @var array<int, array<string, int>> $invoicedByLine */
        $invoicedByLine = [];
        /** @var array<int, array<string, int>> $creditedByLine */
        $creditedByLine = [];
        /** @var array<string, int> $invoicedByRate */
        $invoicedByRate = [];
        /** @var array<string, int> $creditedByRate */
        $creditedByRate = [];
        $positions = [];
        $credited = 0;

        foreach ($invoice->lines as $line) {
            $positions[$line->id] = $line->position;

            foreach ($line->taxes as $tax) {
                $invoicedByLine[$line->id][self::rate($tax)] = ($invoicedByLine[$line->id][self::rate($tax)] ?? 0) + $tax->tax->minorAmount();
                $invoicedByRate[self::rate($tax)] = ($invoicedByRate[self::rate($tax)] ?? 0) + $tax->tax->minorAmount();
            }
        }

        foreach ($invoice->creditNotes as $creditNote) {
            $credited += $creditNote->total->minorAmount();

            foreach ($creditNote->lines as $line) {
                foreach ($line->taxes as $tax) {
                    if ($line->invoice_line_id !== null) {
                        $creditedByLine[$line->invoice_line_id][self::rate($tax)] = ($creditedByLine[$line->invoice_line_id][self::rate($tax)] ?? 0) + $tax->tax->minorAmount();
                    }

                    $creditedByRate[self::rate($tax)] = ($creditedByRate[self::rate($tax)] ?? 0) + $tax->tax->minorAmount();
                }
            }
        }

        $problems = [];

        foreach ($creditedByLine as $lineId => $rates) {
            foreach ($rates as $rate => $tax) {
                if ($tax > ($invoicedByLine[$lineId][$rate] ?? 0)) {
                    $problems[] = $this->problem($invoice, InvoiceTaxProblem::CreditedAboveInvoiced, $positions[$lineId] ?? null, $rate, $tax, $invoicedByLine[$lineId][$rate] ?? 0);
                }
            }
        }

        foreach ($creditedByRate as $rate => $tax) {
            if ($tax > ($invoicedByRate[$rate] ?? 0)) {
                $problems[] = $this->problem($invoice, InvoiceTaxProblem::CreditedAboveInvoiced, null, $rate, $tax, $invoicedByRate[$rate] ?? 0);
            }
        }

        if ($credited === $invoice->total->minorAmount()) {
            foreach ($invoicedByRate as $rate => $tax) {
                if (($creditedByRate[$rate] ?? 0) < $tax) {
                    $problems[] = $this->problem($invoice, InvoiceTaxProblem::TaxLeftWhenFullyCredited, null, $rate, $creditedByRate[$rate] ?? 0, $tax);
                }
            }
        }

        return $problems;
    }

    private function problem(Invoice $invoice, InvoiceTaxProblem $problem, ?int $position, string $rate, int $credited, int $invoiced): InvoiceTaxMismatch
    {
        return new InvoiceTaxMismatch($invoice->order->public_id, $invoice->public_id, $invoice->currency, $problem, $position, $rate, $credited, $invoiced);
    }

    /**
     * A rate as staff read it: its name and percent, then where it applies.
     */
    private static function rate(InvoiceTax|CreditNoteTax $tax): string
    {
        $percent = $tax->rate_percent->toString();
        $percent = str_contains($percent, '.') ? rtrim(rtrim($percent, '0'), '.') : $percent;

        return trim(sprintf('%s %s%% %s %s', $tax->name, $percent, $tax->country_code, $tax->state_code ?? ''));
    }
}
