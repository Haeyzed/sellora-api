<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * Why an order was cancelled.
 */
enum CancellationReason: string
{
    /** Staff cancelled it. */
    case Staff = 'staff';

    /** Its customer cancelled it before paying. */
    case Customer = 'customer';

    /** It wasn't paid by its deadline. */
    case PaymentExpired = 'payment_expired';
}
