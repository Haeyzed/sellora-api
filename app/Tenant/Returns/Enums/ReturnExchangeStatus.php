<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

/**
 * Where a confirmed exchange stands, following its replacement order's exchange credit.
 *
 *   pending → committed (the replacement was paid) | released (it expired or was cancelled unpaid)
 */
enum ReturnExchangeStatus: string
{
    /** Its replacement order is placed and not paid yet. */
    case Pending = 'pending';

    /** Its replacement was paid: what the returned items credited is final. */
    case Committed = 'committed';

    /** Its replacement expired or was cancelled unpaid: the return is free for another exchange, or a refund. */
    case Released = 'released';
}
