<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Legal\Exceptions\LegalDocumentsNotAcceptedException;
use App\Landlord\Tenancy\Actions\StartStoreRegistration;
use App\Landlord\Tenancy\Exceptions\HostingRegionUnavailableException;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationClosedException;
use App\Landlord\Tenancy\Exceptions\StoresPerEmailLimitReachedException;
use App\Landlord\Tenancy\Exceptions\SubdomainTakenException;
use App\Landlord\Tenancy\Http\Requests\StartStoreRegistrationRequest;
use App\Landlord\Tenancy\Http\Resources\StoreRegistrationResource;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registering a new store.
 */
final class StoreRegistrationController extends Controller
{
    /**
     * Start registering a store.
     *
     * Emails a 6-digit code to the given address; the store is created once
     * the code is confirmed. The code works for 60 minutes, and the subdomain
     * is held for this sign-up until then. Send the IDs of every legal
     * document version in force, from the legal documents list.
     *
     * @unauthenticated
     *
     * @throws StoreRegistrationClosedException
     * @throws LegalDocumentsNotAcceptedException
     * @throws HostingRegionUnavailableException
     * @throws SubdomainTakenException
     * @throws StoresPerEmailLimitReachedException
     */
    public function store(StartStoreRegistrationRequest $request, StartStoreRegistration $startStoreRegistration): JsonResponse
    {
        $storeRegistration = $startStoreRegistration->handle($request->registrationData());

        return (new StoreRegistrationResource($storeRegistration))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Check a registration.
     *
     * How far a registration has got. After the code is confirmed the store
     * is set up in the background; poll this until the status is "active".
     *
     * @unauthenticated
     */
    public function show(StoreRegistration $storeRegistration): StoreRegistrationResource
    {
        return new StoreRegistrationResource($storeRegistration->load('tenant'));
    }
}
