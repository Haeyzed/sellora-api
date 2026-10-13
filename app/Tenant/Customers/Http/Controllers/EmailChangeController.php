<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\ConfirmCustomerEmailChange;
use App\Tenant\Customers\Actions\StartCustomerEmailChange;
use App\Tenant\Customers\Exceptions\InvalidCustomerEmailLinkException;
use App\Tenant\Customers\Http\Requests\CustomerEmailLinkRequest;
use App\Tenant\Customers\Http\Requests\StartCustomerEmailChangeRequest;
use App\Tenant\Customers\Http\Resources\CustomerResource;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Response;
use LogicException;

/**
 * Moving the signed-in customer's account to another email.
 */
final class EmailChangeController extends Controller
{
    /**
     * Start an email change.
     *
     * Needs the current password. Emails a link to the new address; nothing
     * changes until the customer uses it while signed in.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function store(StartCustomerEmailChangeRequest $request, StartCustomerEmailChange $startCustomerEmailChange): Response
    {
        $startCustomerEmailChange->handle($request->customer(), $request->string('current_password')->value(), $request->newEmail());

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    /**
     * Confirm an email change.
     *
     * Uses the token from the link sent to the new address, while signed in to
     * the same account. The new address counts as verified, every other
     * device is signed out, and the old address is told.
     *
     * @throws InvalidCustomerEmailLinkException
     */
    public function confirm(CustomerEmailLinkRequest $request, ConfirmCustomerEmailChange $confirmCustomerEmailChange): CustomerResource
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        return new CustomerResource($confirmCustomerEmailChange->handle($customer, $request->token()));
    }
}
