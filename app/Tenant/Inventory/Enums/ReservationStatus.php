<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Enums;

/**
 * Where a stock reservation is in its life. Only an active one holds stock; the others are settled and never change again.
 */
enum ReservationStatus: string
{
    /** Holding stock for a checkout or an order. */
    case Active = 'active';

    /** The stock left the shelf: the order was fulfilled. */
    case Committed = 'committed';

    /** Given back, such as a cancelled order or an abandoned checkout. */
    case Released = 'released';

    /** Given back because its hold ran out, such as a checkout left open too long. */
    case Expired = 'expired';
}
