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

    /** Units the buyer sent back (a return, Step 18) went back on the shelf, ready to sell again. */
    case Restocked = 'restocked';

    /** Units the buyer sent back came back damaged: recorded as they arrive, then written off at once. */
    case ReturnedDamaged = 'returned_damaged';

    /** Units taken off the books because they can't be sold, such as returned units that came back damaged. */
    case WrittenOff = 'written_off';
}
