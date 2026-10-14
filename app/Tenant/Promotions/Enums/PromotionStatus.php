<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Enums;

/**
 * Where a promotion stands now, worked out from its dates, whether it is active and whether it is archived.
 */
enum PromotionStatus: string
{
    /** Active and running: baskets can get it. */
    case Running = 'running';

    /** Active, but its start is still to come. */
    case Scheduled = 'scheduled';

    /** Its end has passed. */
    case Ended = 'ended';

    /** Deactivated by staff; activating it again resumes it within its dates. */
    case Inactive = 'inactive';

    /** Archived: it never applies again, and is kept for the orders that used it. */
    case Archived = 'archived';
}
