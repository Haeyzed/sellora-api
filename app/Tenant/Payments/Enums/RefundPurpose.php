<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * What a refund does to the sale. Only a refund that reduces the sale gets a credit note (Orders, Step 15b).
 */
enum RefundPurpose: string
{
    /** Gives back part or all of what was sold: returned items, shipping, or a price reduction. */
    case ReducesSale = 'reduces_sale';

    /** Gives back money that was never part of the sale: an overpayment, a payment in review, or a payment for an order cancelled before it was paid in full. */
    case ReturnsPayment = 'returns_payment';
}
