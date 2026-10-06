<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Actions\ResendStoreRegistrationCode;
use App\Landlord\Tenancy\Exceptions\StoreRegistrationAlreadyVerifiedException;
use App\Landlord\Tenancy\Exceptions\StoresPerEmailLimitReachedException;
use App\Landlord\Tenancy\Exceptions\SubdomainTakenException;
use App\Landlord\Tenancy\Exceptions\VerificationCodeRecentlySentException;
use App\Landlord\Tenancy\Http\Resources\StoreRegistrationResource;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The verification codes of a store registration.
 */
final class StoreRegistrationCodeController extends Controller
{
    /**
     * Send a new code.
     *
     * Emails a new code, valid for 60 minutes; the previous one stops
     * working. At most one a minute. Also revives an expired registration,
     * if its subdomain is still free.
     *
     * @unauthenticated
     *
     * @throws StoreRegistrationAlreadyVerifiedException
     * @throws VerificationCodeRecentlySentException
     * @throws SubdomainTakenException
     * @throws StoresPerEmailLimitReachedException
     */
    public function store(StoreRegistration $storeRegistration, ResendStoreRegistrationCode $resendStoreRegistrationCode): JsonResponse
    {
        $storeRegistration = $resendStoreRegistrationCode->handle($storeRegistration);

        return (new StoreRegistrationResource($storeRegistration))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
