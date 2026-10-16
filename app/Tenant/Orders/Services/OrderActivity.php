<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Services;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;

/**
 * Writes what staff did to an order in the store's activity log ("orders"), such as "Cancelled order #1042".
 *
 * Entries name the order by its number and list which fields changed, never
 * their values, so the log holds no contact details or addresses.
 */
final readonly class OrderActivity
{
    /**
     * @param  list<string>  $changedFields
     */
    public function record(StaffMember $actor, Order $order, string $event, string $description, array $changedFields = []): void
    {
        $entry = activity('orders')
            ->causedBy($actor)
            ->performedOn($order)
            ->event($event);

        if ($changedFields !== []) {
            $entry->withProperties(['fields' => $changedFields]);
        }

        $entry->log("{$description} order {$order->displayNumber()}");
    }
}
