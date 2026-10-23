<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Enums;

/**
 * Where what a payment method held for an order stands (section 13): held at placement, then committed once the order is paid, or released if it expires or is cancelled unpaid. A committed hold is refunded when the order is cancelled after it was paid, once its method gave it back.
 */
enum PaymentHoldStatus: string
{
    case Held = 'held';

    case Committed = 'committed';

    case Released = 'released';

    case Refunded = 'refunded';

    /**
     * Whether it counts as paid towards the order (a refunded hold was paid, then given back: it also counts as refunded).
     */
    public function covers(): bool
    {
        return $this !== self::Released;
    }
}
