<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Enums;

/**
 * What a shopper may know about a variant's stock: never a quantity (section 14).
 */
enum StockStatus: string
{
    /** Some is available at an active location, or its stock isn't counted. */
    case InStock = 'in_stock';

    /** None is available, but it can be ordered now and sent when it arrives. */
    case Backorder = 'backorder';

    /** None is available, and it can't be ordered until it is back. */
    case OutOfStock = 'out_of_stock';

    public function canBeBought(): bool
    {
        return $this !== self::OutOfStock;
    }
}
