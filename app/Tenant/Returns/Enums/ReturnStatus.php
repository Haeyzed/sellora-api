<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

/**
 * Where a return stands.
 *
 *   requested → approved | rejected | cancelled
 *   approved  → received | cancelled | completed (the buyer keeps the items: refunded without coming back)
 *   received  → inspected
 *   inspected → completed (its refund went back) | rejected (nothing to refund, such as damage the buyer caused)
 *
 * Rejected, completed and cancelled are final.
 */
enum ReturnStatus: string
{
    /** The buyer or staff asked; waiting for staff. */
    case Requested = 'requested';

    /** Staff agreed; the items are on their way back, or the buyer keeps them. */
    case Approved = 'approved';

    /** Staff refused it, with a reason. */
    case Rejected = 'rejected';

    /** The items arrived at the store. */
    case Received = 'received';

    /** Staff checked the items: each unit went back on the shelf or was written off as damaged. */
    case Inspected = 'inspected';

    /** Its refund went back to the buyer. */
    case Completed = 'completed';

    /** Called off by the buyer while it was requested, or by staff before the items arrived. */
    case Cancelled = 'cancelled';

    public function canMoveTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Received, self::Cancelled, self::Completed],
            self::Received => [self::Inspected],
            self::Inspected => [self::Completed, self::Rejected],
            self::Rejected, self::Completed, self::Cancelled => [],
        }, true);
    }

    /**
     * Whether its units still count against what can be returned of their lines: every status but rejected and cancelled.
     */
    public function holdsUnits(): bool
    {
        return ! in_array($this, [self::Rejected, self::Cancelled], true);
    }

    /**
     * The statuses whose units count against what can be returned, as stored values.
     *
     * @return list<string>
     */
    public static function holdingUnits(): array
    {
        return array_values(array_map(static fn (self $status): string => $status->value, array_filter(self::cases(), static fn (self $status): bool => $status->holdsUnits())));
    }
}
