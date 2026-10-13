<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\ChangeCustomerStatus;
use App\Tenant\Customers\Actions\UpdateCustomerAsStaff;
use App\Tenant\Customers\Http\Requests\ListCustomersRequest;
use App\Tenant\Customers\Http\Requests\ManageCustomerRequest;
use App\Tenant\Customers\Http\Requests\UpdateCustomerAsStaffRequest;
use App\Tenant\Customers\Http\Requests\ViewCustomerRequest;
use App\Tenant\Customers\Http\Resources\CustomerAddressResource;
use App\Tenant\Customers\Http\Resources\StaffCustomerResource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Staff managing the store's customer accounts. There is no way for staff to create one: customers sign up themselves.
 */
final class StaffCustomerController extends Controller
{
    /**
     * List customers.
     *
     * Needs the customers.view permission. Newest first.
     */
    public function index(ListCustomersRequest $request): AnonymousResourceCollection
    {
        $query = Customer::query();
        $request->applyFilters($query);

        return StaffCustomerResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show a customer.
     *
     * Needs the customers.view permission. Works for customers in the trash too.
     */
    public function show(ViewCustomerRequest $request, Customer $customer): StaffCustomerResource
    {
        return new StaffCustomerResource($customer);
    }

    /**
     * Update a customer.
     *
     * Needs the customers.manage permission. Changes the name, phone or
     * language; only the fields sent change. Staff never change a customer's
     * email, which would redirect their password resets.
     */
    public function update(UpdateCustomerAsStaffRequest $request, Customer $customer, UpdateCustomerAsStaff $updateCustomerAsStaff): StaffCustomerResource
    {
        return new StaffCustomerResource($updateCustomerAsStaff->handle($request->actor(), $customer, $request->changes()));
    }

    /**
     * Deactivate a customer.
     *
     * Needs the customers.manage permission. Signs them out everywhere at
     * once; they can't sign in until reactivated.
     */
    public function deactivate(ManageCustomerRequest $request, Customer $customer, ChangeCustomerStatus $changeCustomerStatus): StaffCustomerResource
    {
        return new StaffCustomerResource($changeCustomerStatus->deactivate($request->actor(), $customer));
    }

    /**
     * Reactivate a customer.
     *
     * Needs the customers.manage permission.
     */
    public function reactivate(ManageCustomerRequest $request, Customer $customer, ChangeCustomerStatus $changeCustomerStatus): StaffCustomerResource
    {
        return new StaffCustomerResource($changeCustomerStatus->reactivate($request->actor(), $customer));
    }

    /**
     * Move a customer to the trash.
     *
     * Needs the customers.manage permission. Signs them out everywhere. A
     * customer left in the trash for the retention period (90 days by
     * default) is erased for good; restore them before then to keep them.
     */
    public function destroy(ManageCustomerRequest $request, Customer $customer, ChangeCustomerStatus $changeCustomerStatus): Response
    {
        $changeCustomerStatus->trash($request->actor(), $customer);

        return response()->noContent();
    }

    /**
     * Restore a customer from the trash.
     *
     * Needs the customers.manage permission.
     */
    public function restore(ManageCustomerRequest $request, Customer $customer, ChangeCustomerStatus $changeCustomerStatus): StaffCustomerResource
    {
        return new StaffCustomerResource($changeCustomerStatus->restore($request->actor(), $customer));
    }

    /**
     * List a customer's addresses.
     *
     * Needs the customers.view permission. Newest first.
     */
    public function addresses(ViewCustomerRequest $request, Customer $customer): AnonymousResourceCollection
    {
        $addresses = $customer->addresses()->latest('id')->get();

        return CustomerAddressResource::collection($addresses->map(static fn (CustomerAddress $address): CustomerAddressResource => new CustomerAddressResource($address, $customer)));
    }
}
