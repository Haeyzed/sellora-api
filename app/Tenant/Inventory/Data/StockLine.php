<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Data;

use App\Tenant\Catalog\Models\ProductVariant;
use LogicException;

/**
 * How many of a variant a checkout or order wants, in whole units.
 */
final readonly class StockLine
{
    /**
     * @throws LogicException When the quantity isn't a positive whole number, which is a programming mistake.
     */
    public function __construct(
        public ProductVariant $variant,
        public int $quantity,
    ) {
        if ($quantity <= 0) {
            throw new LogicException('A stock line wants a positive number of units.');
        }
    }
}
