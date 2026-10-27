<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Enums;

/**
 * What kind of change a stock movement is.
 */
enum StockMovementType: string
{
    /** Stock arrived, such as a delivery from a supplier. */
    case Received = 'received';

    /** Staff corrected the count, with a reason such as damage or a recount. */
    case Adjusted = 'adjusted';

    /** Held for a checkout or an order, or the amount held changed. */
    case Reserved = 'reserved';

    /** A hold was given back, such as a cancelled order. */
    case Released = 'released';

    /** Held stock left the shelf: the order was fulfilled. */
    case Committed = 'committed';

    /** A hold ran out and was given back, such as a checkout left open. */
    case Expired = 'expired';

    /** Stock that left for an order came back undelivered: on hand again, and held for the order once more. */
    case Returned = 'returned';
}
