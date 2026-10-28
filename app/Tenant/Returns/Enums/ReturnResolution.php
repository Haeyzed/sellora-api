<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

/**
 * How a return is settled. A refund, for now; exchanges come in Step 18b.
 */
enum ReturnResolution: string
{
    /** What the returned units cost is given back through Payments. */
    case Refund = 'refund';
}
