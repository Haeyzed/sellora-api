<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Cart\Enums\CartLineStatus;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Data\DiscountedBasket;

/**
 * A cart as the shopper sees it now: each line, the lines that can be bought priced with their discounts and the chosen shipping, and tax estimated to where the shopper said they are.
 */
final readonly class PricedCart
{
    /**
     * @param  list<PricedCartLine>  $lines  In the cart's order.
     * @param  DiscountedBasket|null  $basket  The lines that can be bought, priced with the chosen shipping; null when there are none.
     * @param  TaxAddress|null  $taxDestination  Where tax was estimated to: a chosen pickup's address, the place the shopper gave, or the store's own country; null when nothing was priced.
     */
    public function __construct(
        public Cart $cart,
        public CartCurrency $currency,
        public array $lines,
        public ?DiscountedBasket $basket,
        public ?TaxAddress $taxDestination,
        public CartShipping $shipping,
    ) {}

    /**
     * Whether checkout can start: there is something in it, every line can be bought, and shipping is settled (nothing ships, or an offered method is chosen).
     */
    public function canCheckOut(): bool
    {
        return $this->lines !== []
            && array_all($this->lines, static fn (PricedCartLine $line): bool => $line->status === CartLineStatus::Available)
            && $this->shipping->isReady();
    }
}
