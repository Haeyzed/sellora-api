<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Enums;

/**
 * What kind of change a stock movement is.
 */
enum StockMovementType: string
{
    /** Stock arrived, such as a delivery from a supplier. */
    case Received = 'received';

    /** Staff corrected the count, with a reason such as damage or a recount. */
    case Adjusted = 'adjusted';
}
