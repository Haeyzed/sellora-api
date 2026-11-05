<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

/**
 * How a return is settled: a refund, or an exchange (Step 18d), other items sent in place of the returned ones.
 */
enum ReturnResolution: string
{
    /** What the returned units cost is given back through Payments. */
    case Refund = 'refund';

    /** Each returned line names the variant sent in its place, as a replacement order the returned items pay toward. */
    case Exchange = 'exchange';
}
