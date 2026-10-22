<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Shipping\Models\ShippingMethod;

/**
 * What Checkout knows that the cart doesn't, for PriceCart to price exactly what will be charged (Step 16): the full address, the method chosen at checkout, the buyer's email, and the exchange rate the quote keeps.
 */
final readonly class CheckoutPricing
{
    /**
     * @param  AddressSnapshot|null  $shippingAddress  Where it ships: tax and shipping are worked out to it, carriers quoted live; null before it is given, or for a pickup.
     * @param  TaxAddress|null  $billingDestination  The billing address, for tax when nothing ships to the buyer; null to fall back to the cart's.
     * @param  ShippingMethod|null  $method  The method chosen at checkout; null before one is chosen. Replaces the cart's.
     * @param  string|null  $email  The buyer's email in lower case, for promotions that look at who buys (first order, a limit per buyer).
     * @param  AppliedExchangeRate|null  $rate  The rate the quote keeps; null for today's. Used only while it is for the cart's currency.
     */
    public function __construct(
        public ?AddressSnapshot $shippingAddress,
        public ?TaxAddress $billingDestination,
        public ?ShippingMethod $method,
        public ?string $email,
        public ?AppliedExchangeRate $rate,
    ) {}
}
