<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Enums;

/**
 * How a store numbers its invoices and credit notes.
 */
enum InvoiceNumbering: string
{
    /** Numbers start again each year, and the year is part of the number: INV-2026-000001. */
    case Yearly = 'yearly';

    /** One series that never starts again: INV-000001. */
    case Continuous = 'continuous';
}
