<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Http\Requests\CustomerPrivacyRequest;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Staff answering a customer's data-protection request, for example one made by email.
 */
final class StaffCustomerPrivacyController extends Controller
{
    /**
     * Get a customer's personal data.
     *
     * Needs the customers.privacy permission. Everything the store holds
     * about the customer, by section, as their own export has it.
     */
    public function show(CustomerPrivacyRequest $request, Customer $customer, PersonalDataRegistry $personalDataRegistry): JsonResponse
    {
        return response()->json(['data' => $personalDataRegistry->export(CustomerDataSubject::of($customer))]);
    }

    /**
     * Erase a customer.
     *
     * Needs the customers.privacy permission and your own password. Erases
     * everything the store holds about the customer and signs them out
     * everywhere; it can't be undone. Past orders stay, without their details.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function destroy(CustomerPrivacyRequest $request, Customer $customer, EraseCustomer $eraseCustomer): Response
    {
        $eraseCustomer->byStaff($request->actor(), $customer, $request->string('password')->value());

        return response()->noContent();
    }
}
