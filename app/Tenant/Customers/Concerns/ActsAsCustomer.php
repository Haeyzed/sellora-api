<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Concerns;

use App\Tenant\Customers\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * The signed-in customer making a request, for form requests in any domain on routes behind auth:customer (public surface, section 6).
 *
 * @mixin FormRequest
 */
trait ActsAsCustomer
{
    /**
     * @throws LogicException When the route isn't behind auth:customer, which is a routing mistake.
     */
    public function customer(): Customer
    {
        $customer = $this->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by the auth:customer middleware.');
        }

        return $customer;
    }
}
