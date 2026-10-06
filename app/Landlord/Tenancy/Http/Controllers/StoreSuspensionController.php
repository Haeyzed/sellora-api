<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Actions\ReactivateStore;
use App\Landlord\Tenancy\Actions\SuspendStore;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Http\Requests\ManageStoreRequest;
use App\Landlord\Tenancy\Http\Requests\SuspendStoreRequest;
use App\Landlord\Tenancy\Http\Resources\ManagedStoreResource;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Http\Controller;

/**
 * Suspending a store from the platform side, for example for fraud.
 */
final class StoreSuspensionController extends Controller
{
    /**
     * Suspend a store.
     *
     * Needs the stores.manage permission. Every feature is suspended whatever
     * the subscription; nothing is deleted. Staff can still sign in,
     * customers see a friendly unavailable response, and what was already
     * started can be finished. Paying the subscription doesn't lift it.
     *
     * @throws StoreStatusConflictException
     */
    public function store(SuspendStoreRequest $request, Tenant $store, SuspendStore $suspendStore): ManagedStoreResource
    {
        return new ManagedStoreResource($suspendStore->handle($request->actor(), $store, $request->reason())->load(['domains', 'subscription.plan']));
    }

    /**
     * Lift a suspension.
     *
     * Needs the stores.manage permission. A store whose subscription is also
     * suspended stays suspended until it is paid.
     *
     * @throws StoreStatusConflictException
     */
    public function destroy(ManageStoreRequest $request, Tenant $store, ReactivateStore $reactivateStore): ManagedStoreResource
    {
        return new ManagedStoreResource($reactivateStore->handle($request->actor(), $store)->load(['domains', 'subscription.plan']));
    }
}
