<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * What a gateway's answer means for a payment, once checked against what was expected.
 */
enum PaymentVerdict: string
{
    /** Succeeded with exactly the amount, currency and mode expected. */
    case Paid = 'paid';
    /** The gateway says it failed, for good. */
    case NotPaid = 'not_paid';
    /** Money arrived, but not as expected: never counts toward the order. */
    case Mismatch = 'mismatch';
    /** No final answer yet, or an answer missing something needed to call it paid. Asked again later. */
    case StillPending = 'still_pending';
}
