<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Writes what staff did with deliveries in the store's activity log ("delivery").
 *
 * Entries name the delivery only by public ID, never the recipient or the
 * address, so the log holds no personal data to erase or leak.
 */
final readonly class DeliveryActivity
{
    public function record(StaffMember $actor, Delivery $delivery, string $event, string $description): void
    {
        activity('delivery')
            ->causedBy($actor)
            ->performedOn($delivery)
            ->event($event)
            ->log("{$description} delivery {$delivery->public_id}");
    }
}
