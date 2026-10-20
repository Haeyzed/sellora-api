<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * What a credit note line gives back.
 */
enum CreditNoteLineKind: string
{
    /** Units of a line returned. */
    case Item = 'item';

    case Shipping = 'shipping';

    /** A price reduction: an amount given back without returning anything, its tax spread across the invoice's rates. */
    case Adjustment = 'adjustment';
}
