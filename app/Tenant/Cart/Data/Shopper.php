<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Customers\Models\Customer;

/**
 * Who a cart request comes from: a signed-in customer, or a guest with the cart token they were given (or none yet).
 *
 * A signed-in customer always gets their own cart; a guest token sent with
 * their sign-in is ignored, except by the merge.
 */
final readonly class Shopper
{
    private function __construct(
        public ?Customer $customer,
        public ?string $token,
    ) {}

    public static function customer(Customer $customer): self
    {
        return new self($customer, null);
    }

    public static function guest(?string $token): self
    {
        return new self(null, $token);
    }
}
