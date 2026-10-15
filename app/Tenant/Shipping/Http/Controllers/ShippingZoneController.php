<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Shipping\Actions\CreateShippingZone;
use App\Tenant\Shipping\Actions\DeleteShippingZone;
use App\Tenant\Shipping\Actions\ReplaceShippingZoneRegions;
use App\Tenant\Shipping\Actions\UpdateShippingZone;
use App\Tenant\Shipping\Exceptions\RestOfWorldZoneExistsException;
use App\Tenant\Shipping\Exceptions\RestOfWorldZoneHasRegionsException;
use App\Tenant\Shipping\Exceptions\ShippingRegionTakenException;
use App\Tenant\Shipping\Exceptions\ShippingZoneNameTakenException;
use App\Tenant\Shipping\Http\Requests\ListShippingZonesRequest;
use App\Tenant\Shipping\Http\Requests\ManageShippingRequest;
use App\Tenant\Shipping\Http\Requests\ReplaceShippingZoneRegionsRequest;
use App\Tenant\Shipping\Http\Requests\SaveShippingZoneRequest;
use App\Tenant\Shipping\Http\Requests\ViewShippingRequest;
use App\Tenant\Shipping\Http\Resources\ShippingZoneResource;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's shipping zones: where it ships to, as countries or states of them, each region in one zone at most.
 *
 * A shopper's address gets the zone listing its state, else the one listing
 * its country, else the rest-of-the-world zone if there is one. Nothing
 * covering it means no shipping there.
 */
final class ShippingZoneController extends Controller
{
    /**
     * List shipping zones.
     *
     * Needs the shipping.view permission. Sorted by name.
     */
    public function index(ListShippingZonesRequest $request): AnonymousResourceCollection
    {
        return ShippingZoneResource::collection(ShippingZone::query()->with('regions')->orderBy('name')->orderBy('id')->paginate($request->perPage()));
    }

    /**
     * Add a shipping zone.
     *
     * Needs the shipping.manage permission. With the regions it covers, or
     * covering the rest of the world. Audited.
     *
     * @throws ShippingZoneNameTakenException
     * @throws ShippingRegionTakenException
     * @throws RestOfWorldZoneExistsException
     * @throws RestOfWorldZoneHasRegionsException
     */
    public function store(SaveShippingZoneRequest $request, CreateShippingZone $createShippingZone): JsonResponse
    {
        $zone = $createShippingZone->handle((string) $request->zoneName(), $request->isActive() ?? true, $request->coversRestOfWorld() ?? false, $request->regions());

        return (new ShippingZoneResource($zone->load('regions')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a shipping zone.
     *
     * Needs the shipping.view permission.
     */
    public function show(ViewShippingRequest $request, ShippingZone $zone): ShippingZoneResource
    {
        return new ShippingZoneResource($zone->load('regions'));
    }

    /**
     * Change a shipping zone.
     *
     * Needs the shipping.manage permission. Its name, whether it ships, and
     * whether it covers the rest of the world; set its regions with PUT
     * .../regions. Audited.
     *
     * @throws ShippingZoneNameTakenException
     * @throws RestOfWorldZoneExistsException
     * @throws RestOfWorldZoneHasRegionsException
     */
    public function update(SaveShippingZoneRequest $request, ShippingZone $zone, UpdateShippingZone $updateShippingZone): ShippingZoneResource
    {
        return new ShippingZoneResource($updateShippingZone->handle($zone, $request->zoneName(), $request->isActive(), $request->coversRestOfWorld())->load('regions'));
    }

    /**
     * Set a zone's regions.
     *
     * Needs the shipping.manage permission. Replaces the countries and states
     * it covers. A region another zone has is refused (409
     * shipping_region_taken, naming that zone). Audited as one change.
     *
     * @throws ShippingRegionTakenException
     * @throws RestOfWorldZoneHasRegionsException
     */
    public function replaceRegions(ReplaceShippingZoneRegionsRequest $request, ShippingZone $zone, ReplaceShippingZoneRegions $replaceRegions): ShippingZoneResource
    {
        return new ShippingZoneResource($replaceRegions->handle($zone, $request->regions())->load('regions'));
    }

    /**
     * Delete a shipping zone.
     *
     * Needs the shipping.manage permission. Its regions get no shipping until
     * another zone takes them. Audited.
     */
    public function destroy(ManageShippingRequest $request, ShippingZone $zone, DeleteShippingZone $deleteShippingZone): Response
    {
        $deleteShippingZone->handle($zone);

        return response()->noContent();
    }
}
