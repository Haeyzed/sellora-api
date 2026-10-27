<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Enums;

/**
 * Which way cash went in a driver's cash ledger.
 */
enum DriverCashEntryType: string
{
    /** The driver took it from a recipient on delivery: they hold more. */
    case Collected = 'collected';

    /** The driver gave it to staff: they hold less. */
    case HandedOver = 'handed_over';
}
