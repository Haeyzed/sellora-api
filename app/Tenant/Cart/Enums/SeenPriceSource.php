<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Enums;

/**
 * Where the unit price a shopper saw for a line came from, which decides how a later price is compared with it.
 */
enum SeenPriceSource: string
{
    /** The merchant's fixed price for the cart's currency: compared in that currency. */
    case Fixed = 'fixed';

    /** The base price, converted at the store's rate (at 1 in the base currency): compared in the base currency, so rates moving never count. */
    case Converted = 'converted';
}
