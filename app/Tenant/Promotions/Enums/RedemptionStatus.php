<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * Where one use of a promotion stands. Reserved and committed uses count against usage limits; released ones don't.
 */
enum RedemptionStatus: string
{
    /** Held for a checkout until it is paid or its hold runs out. */
    case Reserved = 'reserved';

    /** Used by an order that was paid. */
    case Committed = 'committed';

    /** Given back: the checkout's hold ran out, or the order was cancelled. */
    case Released = 'released';
}
