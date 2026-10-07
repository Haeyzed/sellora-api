<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Enums;

/**
 * The unit a store shows weights in. Weights are always stored in metric (section 3.3); this is display only.
 */
enum WeightUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'g';
    case Pound = 'lb';
    case Ounce = 'oz';
}
