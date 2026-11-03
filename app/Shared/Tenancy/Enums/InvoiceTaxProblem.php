<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Enums;

/**
 * What is wrong with the tax credited on an invoice.
 */
enum InvoiceTaxProblem: string
{
    /** More tax was credited than was invoiced, on a line or at a rate. */
    case CreditedAboveInvoiced = 'credited_above_invoiced';

    /** The invoice is credited in full, but some tax at a rate never was. */
    case TaxLeftWhenFullyCredited = 'tax_left_when_fully_credited';
}
