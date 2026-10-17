<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Why money that arrived doesn't count toward its order.
 */
enum ReviewReason: string
{
    /** The gateway reported another amount than expected. */
    case AmountMismatch = 'amount_mismatch';
    /** The gateway reported another currency than expected. */
    case CurrencyMismatch = 'currency_mismatch';
    /** A test payment for a live order, or a live payment for a test order. */
    case ModeMismatch = 'mode_mismatch';
    /** The gateway took the money after saying the payment failed. */
    case SucceededAfterFailing = 'succeeded_after_failing';
}
