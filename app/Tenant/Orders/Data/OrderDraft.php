<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Data;

use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Shipping\Data\ShippingOption;
use Carbon\CarbonImmutable;

/**
 * Everything Checkout hands over to place an order (Step 16): the basket exactly as it was charged, the buyer, the addresses, the chosen shipping, and what the checkout holds.
 *
 * The order saves all of it as it is and never prices anything again.
 */
final readonly class OrderDraft
{
    /**
     * @param  string  $checkoutId  The checkout's public ID: placing the same checkout again returns its order.
     * @param  DiscountedBasket  $basket  The lines priced with their discounts, tax and shipping, as charged.
     * @param  AddressSnapshot|null  $shippingAddress  Needed when something ships, except to a pickup.
     * @param  ShippingOption|null  $shipping  The chosen way to ship, priced; needed exactly when something ships.
     * @param  StockHolder  $stockHolder  The checkout, whose stock hold becomes the order's.
     * @param  RedemptionHolder  $promotionHolder  The checkout, whose promotion uses become the order's.
     * @param  string  $locale  The language names are copied in: the buyer's.
     * @param  CarbonImmutable|null  $paymentDueAt  A later deadline for a payment method paid later (Step 15); null for the usual one. Never none.
     */
    public function __construct(
        public string $checkoutId,
        public DiscountedBasket $basket,
        public OrderBuyer $buyer,
        public ?AddressSnapshot $billingAddress,
        public ?AddressSnapshot $shippingAddress,
        public ?ShippingOption $shipping,
        public StockHolder $stockHolder,
        public RedemptionHolder $promotionHolder,
        public string $locale,
        public ?string $customerNote = null,
        public ?CarbonImmutable $paymentDueAt = null,
    ) {}
}
