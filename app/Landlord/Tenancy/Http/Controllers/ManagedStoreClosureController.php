<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Actions\CloseStore;
use App\Landlord\Tenancy\Actions\RestoreStore;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Http\Requests\CloseStoreRequest;
use App\Landlord\Tenancy\Http\Requests\ManageStoreRequest;
use App\Landlord\Tenancy\Http\Resources\ManagedStoreResource;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccountReference;
use App\Shared\Http\Controller;

/**
 * Closing a store from the platform side, and restoring a closed one before its purge date.
 */
final class ManagedStoreClosureController extends Controller
{
    /**
     * Close a store.
     *
     * Needs the stores.manage permission. Works on active, suspended and
     * failed-to-set-up stores. The store stops opening at once, everyone in
     * it (staff, customers and drivers) is signed out, and its data is kept
     * for 90 days before it may be purged. The owner is emailed the date.
     *
     * @throws StoreStatusConflictException
     */
    public function store(CloseStoreRequest $request, Tenant $store, CloseStore $closeStore): ManagedStoreResource
    {
        $store = $closeStore->handle($store, AccountReference::to($request->actor()), $request->reason(), $request->actor());

        return new ManagedStoreResource($store->load(['domains', 'subscription.plan']));
    }

    /**
     * Restore a closed store.
     *
     * Needs the stores.manage permission. Only before it is purged. The
     * store returns to the status it had before closing: a store suspended
     * then closed comes back suspended. Everyone must sign in again.
     *
     * @throws StoreStatusConflictException
     */
    public function destroy(ManageStoreRequest $request, Tenant $store, RestoreStore $restoreStore): ManagedStoreResource
    {
        return new ManagedStoreResource($restoreStore->handle($request->actor(), $store)->load(['domains', 'subscription.plan']));
    }
}
