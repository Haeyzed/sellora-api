<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Subscriptions\Actions\RemoveLimitOverride;
use App\Landlord\Subscriptions\Actions\SetLimitOverride;
use App\Landlord\Tenancy\Http\Requests\ChangeStoreAllowancesRequest;
use App\Landlord\Tenancy\Http\Requests\SetLimitOverrideRequest;
use App\Landlord\Tenancy\Http\Requests\ViewStoreRequest;
use App\Landlord\Tenancy\Http\Resources\LimitOverrideResource;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Usage limits set for one store instead of its plan's.
 */
final class LimitOverrideController extends Controller
{
    /**
     * List a store's limit overrides.
     *
     * Needs the stores.view permission.
     */
    public function index(ViewStoreRequest $request, Tenant $store): AnonymousResourceCollection
    {
        return LimitOverrideResource::collection($store->limitOverrides()->orderBy('limit_key')->get());
    }

    /**
     * Set a limit override.
     *
     * Needs the stores.grant permission. Replaces any override of the same
     * limit (200), or adds one (201). Send a number in value, or unlimited:
     * true on purpose; a missing or null value is refused, never read as
     * unlimited. A lower limit never deletes anything; it only stops the store
     * creating more.
     */
    public function update(SetLimitOverrideRequest $request, Tenant $store, string $limitKey, SetLimitOverride $setLimitOverride): LimitOverrideResource
    {
        return new LimitOverrideResource($setLimitOverride->handle($request->actor(), $store, $limitKey, $request->overrideValue(), $request->expiresAt(), $request->reason()));
    }

    /**
     * Remove a limit override.
     *
     * Needs the stores.grant permission. The plan's limit applies again.
     */
    public function destroy(ChangeStoreAllowancesRequest $request, Tenant $store, string $limitKey, RemoveLimitOverride $removeLimitOverride): Response
    {
        $removeLimitOverride->handle($request->actor(), $store, $limitKey);

        return response()->noContent();
    }
}
