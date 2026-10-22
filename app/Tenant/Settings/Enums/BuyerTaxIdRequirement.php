<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Enums;

/**
 * Whether buyers must give a tax ID at checkout (Step 16).
 */
enum BuyerTaxIdRequirement: string
{
    /** Buyers may give one; it is copied onto the order and its invoice when they do. */
    case Optional = 'optional';

    /** Buyers must give one, for example in a store selling to businesses. */
    case Required = 'required';
}
