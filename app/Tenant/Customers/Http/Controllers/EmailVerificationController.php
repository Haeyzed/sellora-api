<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\SendCustomerEmailVerification;
use App\Tenant\Customers\Actions\VerifyCustomerEmail;
use App\Tenant\Customers\Exceptions\CustomerEmailAlreadyVerifiedException;
use App\Tenant\Customers\Exceptions\InvalidCustomerEmailLinkException;
use App\Tenant\Customers\Http\Requests\CustomerEmailLinkRequest;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogicException;

/**
 * Proving a customer receives mail at their account's email.
 */
final class EmailVerificationController extends Controller
{
    /**
     * Send a new verification link.
     *
     * Emails the signed-in customer a new link to confirm their email; any
     * earlier link stops working.
     *
     * @throws CustomerEmailAlreadyVerifiedException
     */
    public function store(Request $request, SendCustomerEmailVerification $sendCustomerEmailVerification): Response
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        $sendCustomerEmailVerification->handle($customer);

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    /**
     * Confirm an email.
     *
     * Uses the token from the emailed link. Needs no sign-in, because the link
     * is often opened on another device. Works once, and only while the
     * account still has the address the link was sent to.
     *
     * @unauthenticated
     *
     * @throws InvalidCustomerEmailLinkException
     */
    public function verify(CustomerEmailLinkRequest $request, VerifyCustomerEmail $verifyCustomerEmail): Response
    {
        $verifyCustomerEmail->handle($request->token());

        return response()->noContent();
    }
}
