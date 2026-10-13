<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Money;

/**
 * What Cart, Checkout and Orders ask Pricing to price: lines in a currency, to a destination, with shipping once Step 13 adds it.
 */
final readonly class PricingRequest
{
    /**
     * @param  string  $currency  One the store offers now (FindExchangeRates); the base currency always is.
     * @param  list<PricingLine>  $lines
     * @param  TaxAddress|null  $destination  The shipping address; null before one is known, which prices to the store's own country.
     * @param  Money|null  $shipping  The delivery charge in the quote's currency, after shipping discounts, taxed at the store's shipping tax class.
     * @param  TaxCustomer|null  $customer  The signed-in customer buying (Customer::taxCustomer()); null for a guest.
     */
    public function __construct(
        public string $currency,
        public array $lines,
        public ?TaxAddress $destination = null,
        public ?Money $shipping = null,
        public ?TaxCustomer $customer = null,
    ) {}
}
