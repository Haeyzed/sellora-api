<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Cart\CartToken;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;

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

    /**
     * Who a storefront request comes from: the signed-in customer, else a guest with the X-Cart-Token header (or none).
     */
    public static function fromRequest(Request $request): self
    {
        $customer = $request->user();

        if ($customer instanceof Customer) {
            return self::customer($customer);
        }

        $token = $request->headers->get(CartToken::HEADER);

        return self::guest(is_string($token) && $token !== '' ? $token : null);
    }
}
