<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Enums;

/**
 * Where what a payment method held for an order stands (section 13): held at placement, then committed once the order is paid, or released if it expires or is cancelled. Never both.
 */
enum PaymentHoldStatus: string
{
    case Held = 'held';

    case Committed = 'committed';

    case Released = 'released';

    /**
     * Whether it still counts towards paying for the order.
     */
    public function covers(): bool
    {
        return $this !== self::Released;
    }
}
