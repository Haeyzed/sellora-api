<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Subscriptions\Actions\GrantFeature;
use App\Landlord\Subscriptions\Actions\RevokeFeatureGrant;
use App\Landlord\Subscriptions\Exceptions\FeatureAlreadyGrantedException;
use App\Landlord\Subscriptions\Exceptions\FeatureGrantNotActiveException;
use App\Landlord\Subscriptions\Models\FeatureGrant;
use App\Landlord\Tenancy\Http\Requests\ChangeStoreAllowancesRequest;
use App\Landlord\Tenancy\Http\Requests\GrantFeatureRequest;
use App\Landlord\Tenancy\Http\Requests\ViewStoreRequest;
use App\Landlord\Tenancy\Http\Resources\FeatureGrantResource;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modules and integrations given to one store outside its plan.
 */
final class FeatureGrantController extends Controller
{
    /**
     * List a store's feature grants.
     *
     * Needs the stores.view permission. Active and ended, newest first.
     */
    public function index(ViewStoreRequest $request, Tenant $store): AnonymousResourceCollection
    {
        return FeatureGrantResource::collection($store->featureGrants()->with(['grantedBy', 'revokedBy'])->orderByDesc('id')->get());
    }

    /**
     * Grant a feature.
     *
     * Needs the stores.grant permission. The store has the feature as if its
     * plan included it, until the given date or until revoked. When it ends,
     * the feature is locked like after a downgrade: existing data stays
     * readable, nothing new can be added.
     *
     * @throws FeatureAlreadyGrantedException
     */
    public function store(GrantFeatureRequest $request, Tenant $store, GrantFeature $grantFeature): JsonResponse
    {
        $featureGrant = $grantFeature->handle($request->actor(), $store, $request->featureKey(), $request->reason(), $request->expiresAt());

        return (new FeatureGrantResource($featureGrant->load('grantedBy')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Revoke a feature grant.
     *
     * Needs the stores.grant permission. Ends it now.
     *
     * @throws FeatureGrantNotActiveException
     */
    public function destroy(ChangeStoreAllowancesRequest $request, Tenant $store, FeatureGrant $featureGrant, RevokeFeatureGrant $revokeFeatureGrant): FeatureGrantResource
    {
        return new FeatureGrantResource($revokeFeatureGrant->handle($request->actor(), $featureGrant)->load(['grantedBy', 'revokedBy']));
    }
}
