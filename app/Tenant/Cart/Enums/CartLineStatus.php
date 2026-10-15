<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Enums;

/**
 * Whether a line can be bought now. Lines that can't stay in the cart, left out of its totals, until the shopper removes them.
 */
enum CartLineStatus: string
{
    /** For sale, and in stock or open to backorders. */
    case Available = 'available';

    /** No longer for sale: its product was unpublished, or it lost its price, or it is in the trash. */
    case Unavailable = 'unavailable';

    /** Out of stock, and it doesn't take backorders. */
    case OutOfStock = 'out_of_stock';
}
