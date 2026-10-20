<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * The tax documents a store issues, each numbered in its own series.
 */
enum TaxDocumentKind: string
{
    case Invoice = 'invoice';

    case CreditNote = 'credit_note';
}
