<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Enums;

/**
 * How staff resolved a failed delivery.
 */
enum DeliveryResolution: string
{
    /** The same driver tries again, as a new delivery. */
    case Retried = 'retried';

    /** The driver brought the goods back to the store. */
    case Returned = 'returned';
}
