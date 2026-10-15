<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Data;

use App\Shared\Money\Money;

/**
 * The ways a basket can be shipped to a destination, cheapest decisions left to the shopper.
 */
final readonly class ShippingQuote
{
    /** No zone covers the destination: only pickups, if any, are offered. */
    public const string NO_ZONE = 'no_zone';

    /**
     * @param  bool  $required  False when no line ships (services, digital goods): no method or address is needed.
     * @param  list<ShippingOption>  $options  In the order shoppers should see them: the zone's methods by position, then pickups.
     * @param  string|null  $reason  Why nothing ships there (NO_ZONE), or null.
     * @param  int  $weightGrams  The total weight of the lines that ship.
     * @param  Money  $value  The lines that ship, after discounts, as displayed, in the quote's currency.
     */
    public function __construct(
        public bool $required,
        public array $options,
        public ?string $reason,
        public int $weightGrams,
        public Money $value,
    ) {}

    /**
     * The option for a method, when it is offered.
     */
    public function option(string $methodPublicId): ?ShippingOption
    {
        foreach ($this->options as $option) {
            if ($option->method->public_id === $methodPublicId) {
                return $option;
            }
        }

        return null;
    }
}
