<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\DeleteCustomerAddress;
use App\Tenant\Customers\Actions\SaveCustomerAddress;
use App\Tenant\Customers\Exceptions\CustomerAddressLimitReachedException;
use App\Tenant\Customers\Http\Requests\CustomerAddressRequest;
use App\Tenant\Customers\Http\Resources\CustomerAddressResource;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use LogicException;

/**
 * The signed-in customer's saved addresses. A customer only ever finds their own; anyone else's is "not found".
 */
final class CustomerAddressController extends Controller
{
    /**
     * List my addresses.
     *
     * Newest first. A customer has at most a few dozen, so the list isn't paged.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = $this->customer($request);
        $addresses = $customer->addresses()->latest('id')->get();

        return CustomerAddressResource::collection($addresses->map(static fn (CustomerAddress $address): CustomerAddressResource => new CustomerAddressResource($address, $customer)));
    }

    /**
     * Save an address.
     *
     * @throws CustomerAddressLimitReachedException
     */
    public function store(CustomerAddressRequest $request, SaveCustomerAddress $saveCustomerAddress): JsonResponse
    {
        $customer = $request->customer();
        $address = $saveCustomerAddress->handle($customer, $request->details());

        return (new CustomerAddressResource($address, $customer))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get an address.
     */
    public function show(Request $request, string $address): CustomerAddressResource
    {
        $customer = $this->customer($request);

        return new CustomerAddressResource($this->ownAddress($customer, $address), $customer);
    }

    /**
     * Replace an address.
     *
     * Send the whole address: which fields are required depends on its country.
     */
    public function update(CustomerAddressRequest $request, SaveCustomerAddress $saveCustomerAddress, string $address): CustomerAddressResource
    {
        $customer = $request->customer();
        $saved = $saveCustomerAddress->handle($customer, $request->details(), $this->ownAddress($customer, $address));

        return new CustomerAddressResource($saved, $customer);
    }

    /**
     * Delete an address.
     *
     * Past orders keep their own copy. If it was a default, there is no default any more.
     */
    public function destroy(Request $request, DeleteCustomerAddress $deleteCustomerAddress, string $address): Response
    {
        $deleteCustomerAddress->handle($this->ownAddress($this->customer($request), $address));

        return response()->noContent();
    }

    private function ownAddress(Customer $customer, string $publicId): CustomerAddress
    {
        return $customer->addresses()->where('public_id', $publicId)->firstOrFail();
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        return $customer;
    }
}
