<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Http\Resources\CustomerResource;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use LogicException;

/**
 * The signed-in customer's own account.
 */
final class CurrentCustomerController extends Controller
{
    /**
     * Get the signed-in customer.
     */
    public function __invoke(Request $request): CustomerResource
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        return new CustomerResource($customer);
    }
}
