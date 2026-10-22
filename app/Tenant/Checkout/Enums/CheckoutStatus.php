<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Enums;

/**
 * Where a checkout stands.
 */
enum CheckoutStatus: string
{
    /** The shopper is still filling it in, or hasn't come back. */
    case Open = 'open';

    /** It became an order. */
    case Placed = 'placed';
}
