<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * Whether an order has been paid for. Moved only by Payments (Step 15 adds the partial and refunded states).
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';

    case Paid = 'paid';

    public function canMoveTo(self $next): bool
    {
        return $this === self::Unpaid && $next === self::Paid;
    }
}
