<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Customers\Models\Customer;

/**
 * What payment methods and checkout validators see of a checkout: who is buying, where from, and what the order would come to.
 */
final readonly class CheckoutContext
{
    /**
     * @param  Money  $total  What the order would come to, as quoted.
     * @param  string|null  $buyerCountry  ISO 3166-1 alpha-2: the billing address's country, else the shipping address's; null before either is given.
     * @param  string|null  $email  The buyer's email, in lower case; null before it is given.
     */
    public function __construct(
        public Checkout $checkout,
        public ?Customer $customer,
        public Money $total,
        public ?string $buyerCountry,
        public ?string $email,
    ) {}

    public function currency(): string
    {
        return $this->total->currency();
    }
}
