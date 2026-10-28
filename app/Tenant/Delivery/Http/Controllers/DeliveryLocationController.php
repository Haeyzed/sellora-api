<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Delivery\Http\Requests\ListDeliveryLocationsRequest;
use App\Tenant\Delivery\Http\Resources\DriverLocationResource;
use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Staff following where a delivery's driver went.
 */
final class DeliveryLocationController extends Controller
{
    /**
     * List a delivery's locations.
     *
     * Needs the delivery.view permission. Where the driver was while the
     * delivery was picked up or on the way, oldest first; purged 30 days
     * after the delivery ends.
     */
    public function index(ListDeliveryLocationsRequest $request, Delivery $delivery): AnonymousResourceCollection
    {
        return DriverLocationResource::collection($delivery->locations()->orderBy('id')->cursorPaginate($request->perPage()));
    }
}
