<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Cart\Enums\CartLineStatus;
use App\Tenant\Cart\Models\CartLine;
use App\Tenant\Pricing\Data\PricedLine;

/**
 * One line as the shopper sees it: whether it can be bought, its price and discount when it can, and whether its price moved.
 */
final readonly class PricedCartLine
{
    /**
     * @param  bool  $backorder  It can be bought, but none is in stock: it is sent when it arrives.
     * @param  PricedLine|null  $priced  Null for a line that can't be bought, which is left out of the totals.
     */
    public function __construct(
        public CartLine $line,
        public CartLineStatus $status,
        public bool $backorder,
        public ?PricedLine $priced,
        public ?PriceChange $priceChange,
    ) {}
}
