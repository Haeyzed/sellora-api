<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * Where an order is in its life. Separate from its payment and fulfilment, which have their own statuses.
 */
enum OrderStatus: string
{
    /** Placed and still being paid for or fulfilled. */
    case Open = 'open';

    /** Paid and fulfilled (or nothing to fulfil); set automatically. Final. */
    case Completed = 'completed';

    /** Cancelled by staff, by its customer, or because it wasn't paid in time. Final. */
    case Cancelled = 'cancelled';

    /**
     * Whether an order may move from this status to the other.
     */
    public function canMoveTo(self $next): bool
    {
        return $this === self::Open && $next !== self::Open;
    }
}
