<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Where the gateway says a refund stands.
 */
enum GatewayRefundState: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Pending = 'pending';
    /** The gateway, asked in full, has no refund with our reference. Core sends it again only after hearing so twice, a while apart, and only once. */
    case NotFound = 'not_found';
    /** The gateway's answer can't be tied to exactly this refund: several look like it, one looks like it but for another amount, or less is left to refund than it. Staff look; it is never sent again. */
    case Unclear = 'unclear';
}
