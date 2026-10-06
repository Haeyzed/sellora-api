<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Actions\RetryStoreProvisioning;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Http\Requests\ManageStoreRequest;
use App\Landlord\Tenancy\Http\Resources\ManagedStoreResource;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class RetryStoreProvisioningController extends Controller
{
    /**
     * Retry setting up a store.
     *
     * Needs the stores.manage permission. Only for a store whose setup failed;
     * it carries on in the background from where it stopped.
     *
     * @throws StoreStatusConflictException
     */
    public function __invoke(ManageStoreRequest $request, Tenant $store, RetryStoreProvisioning $retryStoreProvisioning): JsonResponse
    {
        return (new ManagedStoreResource($retryStoreProvisioning->handle($request->actor(), $store)->load(['domains', 'subscription.plan'])))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
