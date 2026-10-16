<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes what staff did with payments in the store's activity log ("payments"), such as "Connected the payment gateway paystack".
 *
 * Entries never hold credentials, card details or amounts typed by hand
 * beyond what the record itself shows.
 */
final readonly class PaymentActivity
{
    /**
     * @param  array<string, string|int|bool|null>  $properties
     */
    public function record(StaffMember $actor, Model $subject, string $event, string $description, array $properties = []): void
    {
        $entry = activity('payments')
            ->causedBy($actor)
            ->performedOn($subject)
            ->event($event);

        if ($properties !== []) {
            $entry->withProperties($properties);
        }

        $entry->log($description);
    }
}
