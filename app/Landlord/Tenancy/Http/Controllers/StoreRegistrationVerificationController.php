<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Actions\RegisterStore;
use App\Landlord\Tenancy\Exceptions\InvalidStoreRegistrationCodeException;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationClosedException;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationExpiredException;
use App\Landlord\Tenancy\Exceptions\StoresPerEmailLimitReachedException;
use App\Landlord\Tenancy\Exceptions\SubdomainTakenException;
use App\Landlord\Tenancy\Http\Requests\VerifyStoreRegistrationRequest;
use App\Landlord\Tenancy\Http\Resources\StoreRegistrationResource;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirming a store registration with its emailed code.
 */
final class StoreRegistrationVerificationController extends Controller
{
    /**
     * Confirm a registration.
     *
     * Creates the store and starts setting it up in the background; check
     * the registration until its status is "active", then sign in on the
     * store's domain with the email and password used to register. After 5
     * wrong codes a new code is needed.
     *
     * @unauthenticated
     *
     * @throws InvalidStoreRegistrationCodeException
     * @throws StoreRegistrationExpiredException
     * @throws StoreRegistrationClosedException
     * @throws SubdomainTakenException
     * @throws StoresPerEmailLimitReachedException
     */
    public function store(VerifyStoreRegistrationRequest $request, StoreRegistration $storeRegistration, RegisterStore $registerStore): JsonResponse
    {
        $registerStore->handle($storeRegistration, $request->code());

        return (new StoreRegistrationResource($storeRegistration->refresh()->load('tenant')))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
