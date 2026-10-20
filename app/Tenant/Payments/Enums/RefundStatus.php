<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Where the part of a refund drawn from one payment stands. Pending and succeeded parts both use up what the payment can still give back.
 */
enum RefundStatus: string
{
    /** Asked of the gateway; it hasn't said it went through yet. */
    case Pending = 'pending';
    /** The money went back, exactly as asked. */
    case Succeeded = 'succeeded';
    /** The gateway refused it or said it failed: nothing went back. */
    case Failed = 'failed';

    /**
     * Whether it still uses up what its payment can give back.
     */
    public function holdsMoney(): bool
    {
        return $this !== self::Failed;
    }

    /**
     * The statuses that use up what a payment can give back.
     *
     * @return list<string>
     */
    public static function holdingMoney(): array
    {
        return [self::Pending->value, self::Succeeded->value];
    }
}
