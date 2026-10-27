<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Writes what staff did to a driver account in the store's activity log ("delivery").
 *
 * Entries name the driver only by public ID and list which fields changed,
 * never their values, so the log holds no personal data to erase or leak.
 */
final readonly class DriverActivity
{
    /**
     * @param  list<string>  $changedFields
     */
    public function record(StaffMember $actor, Driver $driver, string $event, string $description, array $changedFields = []): void
    {
        $entry = activity('delivery')
            ->causedBy($actor)
            ->performedOn($driver)
            ->event($event);

        if ($changedFields !== []) {
            $entry->withProperties(['fields' => $changedFields]);
        }

        $entry->log("{$description} driver {$driver->public_id}");
    }
}
