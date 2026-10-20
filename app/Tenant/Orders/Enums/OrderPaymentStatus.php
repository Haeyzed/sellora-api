<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

use App\Shared\Money\Money;

/**
 * Where an order stands with its money: worked out by Payments from what was paid and refunded, and only ever moved by Payments.
 */
enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    /** Some of the total was paid, such as a bank transfer short of it. */
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    /** Some of what was paid was refunded. */
    case PartiallyRefunded = 'partially_refunded';
    /** Everything paid was refunded. */
    case Refunded = 'refunded';

    /**
     * The status for an order's total, what was paid toward it, and what was refunded. Giving back only what was paid over the total (an overpayment) leaves it paid.
     */
    public static function forAmounts(Money $total, Money $paid, Money $refunded): self
    {
        return match (true) {
            ! $paid->isPositive() => self::Unpaid,
            $refunded->isPositive() && ! $refunded->isLessThan($paid) => self::Refunded,
            ! $paid->subtract($refunded)->isLessThan($total) => self::Paid,
            $refunded->isPositive() => self::PartiallyRefunded,
            default => self::PartiallyPaid,
        };
    }

    /**
     * Whether the store holds some of the buyer's money for it, which a cancelled order then owes back.
     */
    public function holdsMoney(): bool
    {
        return in_array($this, [self::PartiallyPaid, self::Paid, self::PartiallyRefunded], true);
    }

    /**
     * Whether it was paid in full at some point: it can be fulfilled and completed.
     */
    public function wasPaidInFull(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded, self::Refunded], true);
    }
}
