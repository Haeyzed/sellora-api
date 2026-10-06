<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Auth\AccessTokenResource;
use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\RegisterCustomer;
use App\Tenant\Customers\Http\Requests\RegisterCustomerRequest;
use Illuminate\Http\JsonResponse;

/**
 * Creating a shopper account at a store.
 */
final class RegistrationController extends Controller
{
    /**
     * Create a customer account.
     *
     * Creates the account at this store only and signs the shopper in, returning
     * a bearer token valid for 24 hours.
     *
     * @unauthenticated
     */
    public function __invoke(RegisterCustomerRequest $request, RegisterCustomer $registerCustomer): JsonResponse
    {
        $issuedAccessToken = $registerCustomer->handle(
            $request->string('name')->value(),
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->deviceName(),
        );

        return (new AccessTokenResource($issuedAccessToken))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
