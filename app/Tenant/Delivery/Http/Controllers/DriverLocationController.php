<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Delivery\Actions\RecordDriverLocation;
use App\Tenant\Delivery\Exceptions\DeliveryNotUnderWayException;
use App\Tenant\Delivery\Http\Requests\RecordDriverLocationRequest;
use App\Tenant\Delivery\Http\Resources\DriverLocationResource;
use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The driver app sending where the driver is, during one of their deliveries.
 */
final class DriverLocationController extends Controller
{
    /**
     * Send my location.
     *
     * Only while the delivery is picked up or on the way; at most 12 a minute.
     * Staff see the trail; the buyer sees only the latest point, only while
     * the delivery is on the way.
     *
     * @throws DeliveryNotUnderWayException
     */
    public function store(RecordDriverLocationRequest $request, Delivery $delivery, RecordDriverLocation $recordDriverLocation): JsonResponse
    {
        $location = $recordDriverLocation->handle($delivery, $request->latitude(), $request->longitude(), $request->accuracyMeters());

        return (new DriverLocationResource($location))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
