<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Enums;

/**
 * The answer a consent event records.
 */
enum MarketingConsentStatus: string
{
    case Granted = 'granted';

    case Withdrawn = 'withdrawn';
}
