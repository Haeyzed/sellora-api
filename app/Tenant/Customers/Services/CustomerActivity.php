<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Services;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Writes what staff did to a customer account in the store's activity log ("customers").
 *
 * Entries name the customer only by public ID and list which fields changed,
 * never their values, so the log holds no personal data to erase or leak.
 */
final readonly class CustomerActivity
{
    /**
     * @param  list<string>  $changedFields
     */
    public function record(StaffMember $actor, Customer $customer, string $event, string $description, array $changedFields = []): void
    {
        $entry = activity('customers')
            ->causedBy($actor)
            ->performedOn($customer)
            ->event($event);

        if ($changedFields !== []) {
            $entry->withProperties(['fields' => $changedFields]);
        }

        $entry->log("{$description} customer {$customer->public_id}");
    }
}
