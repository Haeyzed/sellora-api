<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Http\Requests\ListStoresRequest;
use App\Landlord\Tenancy\Http\Requests\ViewStoreRequest;
use App\Landlord\Tenancy\Http\Resources\ManagedStoreResource;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Every store on the platform, for Sellora's team.
 */
final class ManagedStoreController extends Controller
{
    /**
     * List stores.
     *
     * Needs the stores.view permission. Newest first, optionally by status or
     * matching part of the name, a domain or the owner's email.
     */
    public function index(ListStoresRequest $request): AnonymousResourceCollection
    {
        $search = $request->search();
        $pattern = $search === null ? null : '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

        $stores = Tenant::query()
            ->with(['domains', 'subscription.plan'])
            ->when($request->status() !== null, static fn (Builder $query) => $query->where('status', $request->status()))
            ->when($pattern !== null, static fn (Builder $query) => $query->where(static function (Builder $query) use ($pattern): void {
                $query->whereRaw('lower(name) like ?', [$pattern])
                    ->orWhere('owner_email', 'like', $pattern)
                    ->orWhereHas('domains', static fn (Builder $query) => $query->where('domain', 'like', $pattern));
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage());

        return ManagedStoreResource::collection($stores);
    }

    /**
     * Show a store.
     *
     * Needs the stores.view permission.
     */
    public function show(ViewStoreRequest $request, Tenant $store): ManagedStoreResource
    {
        return new ManagedStoreResource($store->load(['domains', 'subscription.plan', 'databaseServer']));
    }
}
