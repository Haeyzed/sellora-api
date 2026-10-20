<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * What an invoice line is for.
 */
enum InvoiceLineKind: string
{
    case Item = 'item';

    case Shipping = 'shipping';
}
