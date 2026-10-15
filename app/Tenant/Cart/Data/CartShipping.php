<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Shared\Money\Money;
use App\Tenant\Shipping\Data\ShippingOption;
use App\Tenant\Shipping\Data\ShippingQuote;
use App\Tenant\Shipping\Data\ShippingRegion;
use App\Tenant\Shipping\Models\ShippingMethod;

/**
 * A cart's shipping as it stands: what it is offered to where the shopper is, the method they chose, and what that costs. An estimate until checkout has the full address.
 */
final readonly class CartShipping
{
    /** Methods are offered, but none (still offered) is chosen. */
    public const string METHOD_REQUIRED = 'shipping_method_required';

    /** Nothing is offered for this destination and these lines, including in a store with no zones yet. */
    public const string NOT_AVAILABLE = 'shipping_not_available';

    /**
     * @param  ShippingRegion  $destination  The country (and state) it was quoted to: the shopper's, or the store's own.
     * @param  ShippingMethod|null  $chosen  The method the shopper chose, offered or not.
     * @param  ShippingOption|null  $option  The chosen method's price, when it is offered.
     * @param  Money|null  $discount  What free-shipping promotions take off it; null when nothing is charged for shipping.
     */
    public function __construct(
        public ShippingQuote $quote,
        public ShippingRegion $destination,
        public ?ShippingMethod $chosen,
        public ?ShippingOption $option,
        public ?Money $discount,
    ) {}

    /**
     * Whether the shopper chose a method the cart isn't offered (any more).
     */
    public function chosenUnavailable(): bool
    {
        return $this->chosen !== null && $this->option === null;
    }

    /**
     * Why shipping stops checkout: METHOD_REQUIRED or NOT_AVAILABLE; null when it is settled (nothing ships, or an offered method is chosen).
     */
    public function blocker(): ?string
    {
        if (! $this->quote->required || $this->option !== null) {
            return null;
        }

        return $this->quote->options === [] ? self::NOT_AVAILABLE : self::METHOD_REQUIRED;
    }
}
