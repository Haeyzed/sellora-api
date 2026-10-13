<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\UpdateCustomerProfile;
use App\Tenant\Customers\Http\Requests\UpdateCustomerProfileRequest;
use App\Tenant\Customers\Http\Resources\CustomerResource;

/**
 * The signed-in customer's own profile.
 */
final class CustomerAccountController extends Controller
{
    /**
     * Update my profile.
     *
     * Changes the name, phone or language; only the fields sent change. The
     * email changes through an emailed link instead (email changes).
     */
    public function update(UpdateCustomerProfileRequest $request, UpdateCustomerProfile $updateCustomerProfile): CustomerResource
    {
        return new CustomerResource($updateCustomerProfile->handle($request->customer(), $request->changes()));
    }
}
