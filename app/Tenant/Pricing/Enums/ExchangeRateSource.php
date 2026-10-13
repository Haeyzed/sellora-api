<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Enums;

/**
 * Where the rate a price was converted at came from, saved with orders so later rate changes never alter them.
 */
enum ExchangeRateSource: string
{
    /** The store's base currency: no conversion. */
    case Base = 'base';

    /** The platform's market rate, plus the store's margin. */
    case Market = 'market';

    /** A rate the merchant set. */
    case Manual = 'manual';
}
