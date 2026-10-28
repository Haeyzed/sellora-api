<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Delivery\Http\Requests\CustomerDeliveryTrackingRequest;
use App\Tenant\Delivery\Http\Requests\GuestDeliveryTrackingRequest;
use App\Tenant\Delivery\Http\Resources\DeliveryTrackingResource;
use App\Tenant\Delivery\Services\DeliveryTracking;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The buyer following the store's own deliveries of their order: only while a delivery is on the way, its driver's first name and latest location. Never proof of delivery.
 */
final class DeliveryTrackingController extends Controller
{
    /**
     * Follow my order's delivery.
     *
     * Poll it: each delivery on the way with the driver's first name and
     * where they were last seen. Empty while no delivery is on the way.
     *
     * @throws ModelNotFoundException When the order isn't theirs.
     */
    public function customer(CustomerDeliveryTrackingRequest $request, Order $order, DeliveryTracking $deliveryTracking): DeliveryTrackingResource
    {
        $order = $request->ownOrder($order);

        return new DeliveryTrackingResource($order, $deliveryTracking->onTheWay($order));
    }

    /**
     * Follow my guest order's delivery.
     *
     * With the order's access token in the X-Order-Token header, never the
     * URL. Poll it: each delivery on the way with the driver's first name and
     * where they were last seen. Empty while no delivery is on the way.
     *
     * @throws ModelNotFoundException When the token is missing, expired or not this order's.
     */
    public function guest(GuestDeliveryTrackingRequest $request, Order $order, DeliveryTracking $deliveryTracking): DeliveryTrackingResource
    {
        $order = $request->guestOrder($order);

        return new DeliveryTrackingResource($order, $deliveryTracking->onTheWay($order));
    }
}
