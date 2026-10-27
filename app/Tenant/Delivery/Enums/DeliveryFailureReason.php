<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Enums;

/**
 * Why a driver couldn't deliver.
 */
enum DeliveryFailureReason: string
{
    case RecipientNotAvailable = 'recipient_not_available';

    case AddressNotFound = 'address_not_found';

    /** The recipient refused the goods. */
    case Refused = 'refused';

    /** It wasn't safe to deliver. */
    case Unsafe = 'unsafe';

    case RecipientAskedToReschedule = 'recipient_asked_to_reschedule';

    /** Anything else; a note saying what is required. */
    case Other = 'other';
}
