<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Enums;

/**
 * Where a delivery is: assigned → picked_up → on_the_way → delivered, or failed on the way. An assigned one can be cancelled.
 */
enum DeliveryStatus: string
{
    /** Given to a driver, who hasn't taken the goods yet. */
    case Assigned = 'assigned';

    /** The driver took the goods from the store. */
    case PickedUp = 'picked_up';

    /** The driver is on the way to the buyer. */
    case OnTheWay = 'on_the_way';

    /** The goods reached the buyer. Final. */
    case Delivered = 'delivered';

    /** The driver couldn't deliver; the goods are still with them until staff resolve it. */
    case Failed = 'failed';

    /** Taken back from the driver before they picked the goods up. Final. */
    case Cancelled = 'cancelled';

    /**
     * Whether a delivery may move from this status to the other.
     */
    public function canMoveTo(self $next): bool
    {
        return match ($this) {
            self::Assigned => $next === self::PickedUp || $next === self::Cancelled,
            self::PickedUp => $next === self::OnTheWay || $next === self::Delivered || $next === self::Failed,
            self::OnTheWay => $next === self::Delivered || $next === self::Failed,
            self::Delivered, self::Failed, self::Cancelled => false,
        };
    }

    /**
     * Whether the driver has the goods in this status (a failed one, until it is resolved).
     */
    public function holdsGoods(): bool
    {
        return in_array($this, [self::PickedUp, self::OnTheWay, self::Failed], true);
    }

    /**
     * The statuses a driver may move their own delivery to.
     *
     * @return list<self>
     */
    public static function driverMoves(): array
    {
        return [self::PickedUp, self::OnTheWay, self::Delivered, self::Failed];
    }
}
