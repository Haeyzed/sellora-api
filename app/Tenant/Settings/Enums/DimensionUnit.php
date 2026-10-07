<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Enums;

/**
 * The unit a store shows lengths, widths and heights in. Dimensions are always stored in metric (section 3.3); this is display only.
 */
enum DimensionUnit: string
{
    case Centimetre = 'cm';
    case Millimetre = 'mm';
    case Metre = 'm';
    case Inch = 'in';
}
