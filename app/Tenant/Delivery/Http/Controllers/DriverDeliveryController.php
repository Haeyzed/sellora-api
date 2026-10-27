<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Auth\AccountReference;
use App\Shared\Http\Controller;
use App\Tenant\Delivery\Actions\MoveDelivery;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Http\Requests\DeliveredRequest;
use App\Tenant\Delivery\Http\Requests\DeliveryFailedRequest;
use App\Tenant\Delivery\Http\Requests\DriverDeliveryRequest;
use App\Tenant\Delivery\Http\Requests\ListDriverDeliveriesRequest;
use App\Tenant\Delivery\Http\Resources\DriverDeliveryResource;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The driver app: the signed-in driver's own deliveries, and moving them along.
 *
 * A driver only ever sees deliveries assigned to them; another driver's is
 * not found.
 */
final class DriverDeliveryController extends Controller
{
    private const array WITH = ['shipment.order.addresses', 'shipment.lines.orderLine', 'shipment.stockLocation'];

    /**
     * List my deliveries.
     *
     * Newest first.
     */
    public function index(ListDriverDeliveriesRequest $request): AnonymousResourceCollection
    {
        $query = Delivery::query()->with(self::WITH)->where('driver_id', $request->driver()->id);
        $open = $request->openFilter();

        if ($open !== null) {
            $open ? $query->open() : $query->whereNot(static fn ($closed) => $closed->open());
        }

        return DriverDeliveryResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show one of my deliveries.
     */
    public function show(DriverDeliveryRequest $request, Delivery $delivery): DriverDeliveryResource
    {
        return new DriverDeliveryResource($delivery->load(self::WITH));
    }

    /**
     * Pick up a delivery.
     *
     * I've taken the goods from the store: they leave the shelf.
     *
     * @throws DeliveryTransitionNotAllowedException
     * @throws ShipmentTransitionNotAllowedException
     * @throws InsufficientStockException
     */
    public function pickUp(DriverDeliveryRequest $request, Delivery $delivery, MoveDelivery $moveDelivery): DriverDeliveryResource
    {
        return new DriverDeliveryResource($moveDelivery->pickUp($delivery, AccountReference::to($request->driver()))->load(self::WITH));
    }

    /**
     * Set off with a delivery.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function onTheWay(DriverDeliveryRequest $request, Delivery $delivery, MoveDelivery $moveDelivery): DriverDeliveryResource
    {
        return new DriverDeliveryResource($moveDelivery->onTheWay($delivery, AccountReference::to($request->driver()))->load(self::WITH));
    }

    /**
     * Hand over a delivery.
     *
     * With the name of who took the goods.
     *
     * @throws DeliveryTransitionNotAllowedException
     * @throws ShipmentTransitionNotAllowedException
     */
    public function delivered(DeliveredRequest $request, Delivery $delivery, MoveDelivery $moveDelivery): DriverDeliveryResource
    {
        return new DriverDeliveryResource($moveDelivery->deliver($delivery, AccountReference::to($request->driver()), $request->recipientName())->load(self::WITH));
    }

    /**
     * Report a delivery failed.
     *
     * Say why. Keep the goods until the store tells you to try again or to
     * bring them back.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function failed(DeliveryFailedRequest $request, Delivery $delivery, MoveDelivery $moveDelivery): DriverDeliveryResource
    {
        return new DriverDeliveryResource($moveDelivery->fail($delivery, AccountReference::to($request->driver()), $request->failureReason(), $request->failureNote())->load(self::WITH));
    }
}
