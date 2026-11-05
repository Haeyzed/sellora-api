<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

use App\Tenant\Promotions\Data\DiscountedBasket;

/**
 * Lines priced with their promotions and, once a way to ship is chosen and priced, its delivery charge: what PriceBasketWithShipping works out.
 */
final readonly class ShippedBasket
{
    /**
     * @param  DiscountedBasket|null  $basket  The lines priced with their promotions, tax and the chosen delivery (when it is priced); null when there are no lines.
     * @param  ShippingQuote  $quote  Every way to ship the lines to the destination, priced by their weight and value after discounts.
     * @param  ShippingOption|null  $option  The chosen way, as quoted; null when none was chosen or it isn't offered there.
     */
    public function __construct(
        public ?DiscountedBasket $basket,
        public ShippingQuote $quote,
        public ?ShippingOption $option,
    ) {}
}
