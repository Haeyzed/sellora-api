<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Enums;

/**
 * Why staff changed a stock count by hand.
 */
enum AdjustmentReason: string
{
    /** A count found a different number than recorded. */
    case Recount = 'recount';

    /** Broken or spoiled, so it can't be sold. */
    case Damaged = 'damaged';

    /** Missing, such as lost or stolen. */
    case Lost = 'lost';

    /** Turned up again. */
    case Found = 'found';

    /** Taken out for use, such as samples or display. */
    case InternalUse = 'internal_use';

    /** Anything else; say why in the note. */
    case Other = 'other';
}
