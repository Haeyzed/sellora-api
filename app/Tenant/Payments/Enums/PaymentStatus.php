<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Enums;

/**
 * Where a payment stands. Only "succeeded" counts toward its order.
 */
enum PaymentStatus: string
{
    /** Started; money may still arrive. */
    case Pending = 'pending';
    /** The money arrived, exactly as expected. */
    case Succeeded = 'succeeded';
    /** The gateway said it failed, for good. */
    case Failed = 'failed';
    /** Money arrived that doesn't match what was expected (amount, currency, or test against live). Never counts toward the order; staff refund what was received. */
    case NeedsReview = 'needs_review';
    /** The gateway still had no final answer when checking stopped. Staff are told and can ask again. */
    case Unresolved = 'unresolved';
    /** No longer expected, such as a bank transfer whose order expired. Money that arrives anyway is still recorded. */
    case Cancelled = 'cancelled';

    /**
     * Whether money may still arrive for it, so it keeps its gateway connected.
     */
    public function isInMotion(): bool
    {
        return $this === self::Pending || $this === self::Unresolved;
    }

    /**
     * The statuses the gateway is still asked about.
     *
     * @return list<self>
     */
    public static function inMotion(): array
    {
        return [self::Pending, self::Unresolved];
    }
}
