<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\SetCustomerTaxId;
use App\Tenant\Customers\Http\Requests\SetCustomerTaxIdRequest;
use App\Tenant\Customers\Http\Resources\CustomerResource;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use LogicException;

/**
 * The signed-in customer's tax ID, for buying as a business.
 */
final class CustomerTaxIdController extends Controller
{
    /**
     * Set my tax ID.
     *
     * Only its format is checked. The store decides whether to make you
     * tax-exempt; changing the ID ends an exemption until the store checks
     * the new one.
     */
    public function update(SetCustomerTaxIdRequest $request, SetCustomerTaxId $setCustomerTaxId): CustomerResource
    {
        return new CustomerResource($setCustomerTaxId->handle($request->customer(), $request->string('tax_id')->value(), $request->string('country')->value()));
    }

    /**
     * Remove my tax ID.
     *
     * Ends a tax exemption too.
     */
    public function destroy(Request $request, SetCustomerTaxId $setCustomerTaxId): CustomerResource
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        return new CustomerResource($setCustomerTaxId->handle($customer, null, null));
    }
}
