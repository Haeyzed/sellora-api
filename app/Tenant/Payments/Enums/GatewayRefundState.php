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
    /** The gateway has no refund with our reference: it never received the request, so sending it again with the same reference is safe. */
    case NotFound = 'not_found';
}
