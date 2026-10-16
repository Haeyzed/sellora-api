<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Where the gateway says a payment stands.
 */
enum GatewayPaymentState: string
{
    /** The gateway took the money; still checked against the amount, currency and mode expected. */
    case Succeeded = 'succeeded';
    /** The gateway says the payment failed or was abandoned, for good. */
    case Failed = 'failed';
    /** Not finished yet, such as a bank transfer or USSD payment on its way. */
    case Pending = 'pending';
    /** The gateway answered something we can't read as any of the above. Treated as pending: never as paid, never as failed. */
    case Unknown = 'unknown';
}
