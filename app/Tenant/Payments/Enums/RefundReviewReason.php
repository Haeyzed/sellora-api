<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Why part of a refund waits for staff: the gateway's answers can't tell whether it was made, so it is never sent again, and still uses up what its payment can give back.
 */
enum RefundReviewReason: string
{
    /** Sending it went unanswered, and the gateway couldn't then be asked whether it was made. */
    case LookupFailed = 'lookup_failed';
    /** The gateway's answer can't be tied to exactly this refund: several refunds look like it, one looks like it but for another amount, or less is left to refund than it. */
    case GatewayUnclear = 'gateway_unclear';
    /** Sent again after the gateway twice reported having none, and still not found. */
    case NotFoundAfterResend = 'not_found_after_resend';
    /** The gateway refused the store's keys when it was sent: it wasn't made, and it waits for staff once the merchant reconnects. */
    case CredentialsRefused = 'credentials_refused';
}
