<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Enums;

/**
 * What a method's price depends on.
 */
enum ShippingPricing: string
{
    /** One price, whatever the order. */
    case Flat = 'flat';

    /** Brackets of the total weight, in grams, of the lines that ship. */
    case Weight = 'weight';

    /** Brackets of the value of the lines that ship, after discounts, as displayed, in the base currency's minor units. */
    case OrderValue = 'order_value';

    /** A carrier's live rate for one of its services, through an integration; quoted only once the full address is known. */
    case Carrier = 'carrier';
}
