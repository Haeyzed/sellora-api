<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * How much of an order has reached, or left for, the shopper. Worked out from its shipments, never set by hand.
 */
enum FulfilmentStatus: string
{
    /** Something ships and none of it has left yet. */
    case Unfulfilled = 'unfulfilled';

    /** Some of what ships has left. */
    case PartiallyFulfilled = 'partially_fulfilled';

    /** Everything that ships has left (or been collected). */
    case Fulfilled = 'fulfilled';

    /** Nothing in it ships (services, digital goods). */
    case NotRequired = 'not_required';

    /**
     * Whether nothing is left to send: everything shipped, or nothing ships.
     */
    public function isDone(): bool
    {
        return $this === self::Fulfilled || $this === self::NotRequired;
    }
}
