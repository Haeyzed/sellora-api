<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\ManageCustomerGroups;
use App\Tenant\Customers\Exceptions\CustomerGroupNameTakenException;
use App\Tenant\Customers\Http\Requests\AddCustomerGroupMembersRequest;
use App\Tenant\Customers\Http\Requests\ListCustomerGroupMembersRequest;
use App\Tenant\Customers\Http\Requests\ListCustomerGroupsRequest;
use App\Tenant\Customers\Http\Requests\ManageCustomerGroupRequest;
use App\Tenant\Customers\Http\Requests\SaveCustomerGroupRequest;
use App\Tenant\Customers\Http\Requests\ViewCustomerGroupRequest;
use App\Tenant\Customers\Http\Resources\CustomerGroupResource;
use App\Tenant\Customers\Http\Resources\StaffCustomerResource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Customer groups, such as "Wholesale" or "VIP", which staff put customers in by hand and promotions can be limited to.
 */
final class CustomerGroupController extends Controller
{
    /**
     * List customer groups.
     *
     * Needs the customers.view permission. Sorted by name.
     */
    public function index(ListCustomerGroupsRequest $request): AnonymousResourceCollection
    {
        $query = CustomerGroup::query()->withCount('members');
        $request->applyFilters($query);

        return CustomerGroupResource::collection($query->orderBy('name')->orderBy('id')->paginate($request->perPage()));
    }

    /**
     * Add a customer group.
     *
     * Needs the customers.manage permission. Audited.
     *
     * @throws CustomerGroupNameTakenException
     */
    public function store(SaveCustomerGroupRequest $request, ManageCustomerGroups $manageCustomerGroups): JsonResponse
    {
        $group = $manageCustomerGroups->create($request->groupName(), $request->description());

        return (new CustomerGroupResource($group))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a customer group.
     *
     * Needs the customers.view permission.
     */
    public function show(ViewCustomerGroupRequest $request, CustomerGroup $customerGroup): CustomerGroupResource
    {
        return new CustomerGroupResource($customerGroup->loadCount('members'));
    }

    /**
     * Change a customer group.
     *
     * Needs the customers.manage permission. Send only what changes. Audited.
     *
     * @throws CustomerGroupNameTakenException
     */
    public function update(SaveCustomerGroupRequest $request, CustomerGroup $customerGroup, ManageCustomerGroups $manageCustomerGroups): CustomerGroupResource
    {
        return new CustomerGroupResource($manageCustomerGroups->update($customerGroup, $request->changes())->loadCount('members'));
    }

    /**
     * Delete a customer group.
     *
     * Needs the customers.manage permission. Its customers stay; they are
     * only no longer in it. A promotion limited to it is no longer limited to
     * it. Audited.
     */
    public function destroy(ManageCustomerGroupRequest $request, CustomerGroup $customerGroup, ManageCustomerGroups $manageCustomerGroups): Response
    {
        $manageCustomerGroups->delete($customerGroup);

        return response()->noContent();
    }

    /**
     * List a group's customers.
     *
     * Needs the customers.view permission. Most recently added first.
     */
    public function members(ListCustomerGroupMembersRequest $request, CustomerGroup $customerGroup): AnonymousResourceCollection
    {
        $members = $customerGroup->members()
            ->orderByDesc('customer_group_members.created_at')
            ->orderByDesc('customers.id')
            ->cursorPaginate($request->perPage());

        return StaffCustomerResource::collection($members);
    }

    /**
     * Add customers to a group.
     *
     * Needs the customers.manage permission. Up to 100 at once; customers
     * already in the group are left as they are. Logged in each customer's
     * activity.
     *
     * @response array{added: int}
     */
    public function addMembers(AddCustomerGroupMembersRequest $request, CustomerGroup $customerGroup, ManageCustomerGroups $manageCustomerGroups): JsonResponse
    {
        return new JsonResponse(['added' => $manageCustomerGroups->addMembers($request->actor(), $customerGroup, $request->customers())]);
    }

    /**
     * Remove a customer from a group.
     *
     * Needs the customers.manage permission. Logged in the customer's activity.
     */
    public function removeMember(ManageCustomerGroupRequest $request, CustomerGroup $customerGroup, Customer $customer, ManageCustomerGroups $manageCustomerGroups): Response
    {
        $manageCustomerGroups->removeMember($request->actor(), $customerGroup, $customer);

        return response()->noContent();
    }
}
