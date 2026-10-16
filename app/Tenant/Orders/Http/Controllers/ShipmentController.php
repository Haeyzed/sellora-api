<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Auth\AccountReference;
use App\Shared\Http\Controller;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Actions\UpdateShipmentTracking;
use App\Tenant\Orders\Exceptions\OrderCannotBeFulfilledException;
use App\Tenant\Orders\Exceptions\ShipmentClosedException;
use App\Tenant\Orders\Exceptions\ShipmentQuantityTooHighException;
use App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException;
use App\Tenant\Orders\Exceptions\TrackingNumberRequiredException;
use App\Tenant\Orders\Http\Requests\CreateShipmentRequest;
use App\Tenant\Orders\Http\Requests\FulfilOrderRequest;
use App\Tenant\Orders\Http\Requests\ListShipmentsRequest;
use App\Tenant\Orders\Http\Requests\UpdateShipmentTrackingRequest;
use App\Tenant\Orders\Http\Requests\ViewOrderRequest;
use App\Tenant\Orders\Http\Resources\ShipmentResource;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Staff fulfilling orders: shipments, their tracking, and where they are.
 */
final class ShipmentController extends Controller
{
    /**
     * List shipments.
     *
     * Needs the orders.view permission. Newest first.
     */
    public function index(ListShipmentsRequest $request): AnonymousResourceCollection
    {
        $query = Shipment::query()->with(['order', 'lines.orderLine']);
        $request->applyFilters($query);

        return ShipmentResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * List an order's shipments.
     *
     * Needs the orders.view permission. Oldest first.
     */
    public function forOrder(ViewOrderRequest $request, Order $order): AnonymousResourceCollection
    {
        return ShipmentResource::collection($order->shipments()->with(['order', 'lines.orderLine'])->get());
    }

    /**
     * Show a shipment.
     *
     * Needs the orders.view permission.
     */
    public function show(ViewOrderRequest $request, Shipment $shipment): ShipmentResource
    {
        return new ShipmentResource($shipment->load(['order', 'lines.orderLine']));
    }

    /**
     * Start a shipment.
     *
     * Needs the orders.fulfil permission and an Idempotency-Key. Only for an
     * open, paid order, and at most what is left of each line that ships:
     * shipments made at the same moment never send more than was bought. It
     * ships the way the buyer chose.
     *
     * @throws OrderCannotBeFulfilledException
     * @throws ShipmentQuantityTooHighException
     */
    #[HeaderParameter('Idempotency-Key', 'A new key for each shipment, 8 to 128 letters, digits, - or _. Sending the same request again with it returns the first answer instead of making a second shipment.', required: true, type: 'string', example: 'ship-2f9c1a7e')]
    public function store(CreateShipmentRequest $request, Order $order, CreateShipment $createShipment): JsonResponse
    {
        $shipment = $createShipment->handle($order, $request->quantities(), AccountReference::to($request->actor()), $request->trackingNumber(), $request->trackingUrl());

        return (new ShipmentResource($shipment->load('order')))->response()->setStatusCode(201);
    }

    /**
     * Change a shipment's tracking.
     *
     * Needs the orders.fulfil permission. Until it arrives or is cancelled.
     *
     * @throws ShipmentClosedException
     */
    public function update(UpdateShipmentTrackingRequest $request, Shipment $shipment, UpdateShipmentTracking $updateShipmentTracking): ShipmentResource
    {
        return new ShipmentResource($updateShipmentTracking->handle($request->actor(), $shipment, $request->trackingNumber(), $request->trackingUrl())->load('order'));
    }

    /**
     * Mark a pickup ready.
     *
     * Needs the orders.fulfil permission. The buyer can come and collect it.
     *
     * @throws ShipmentTransitionNotAllowedException
     */
    public function ready(FulfilOrderRequest $request, Shipment $shipment, MoveShipment $moveShipment): ShipmentResource
    {
        return new ShipmentResource($moveShipment->ready($shipment, AccountReference::to($request->actor()))->load('order'));
    }

    /**
     * Mark a shipment shipped.
     *
     * Needs the orders.fulfil permission. Its stock leaves the shelf. A carrier
     * shipment needs its tracking number first.
     *
     * @throws ShipmentTransitionNotAllowedException
     * @throws TrackingNumberRequiredException
     * @throws InsufficientStockException
     */
    public function ship(FulfilOrderRequest $request, Shipment $shipment, MoveShipment $moveShipment): ShipmentResource
    {
        return new ShipmentResource($moveShipment->ship($shipment, AccountReference::to($request->actor()))->load('order'));
    }

    /**
     * Mark a shipment delivered, or a pickup collected.
     *
     * Needs the orders.fulfil permission. A collected pickup's stock leaves
     * the shelf now.
     *
     * @throws ShipmentTransitionNotAllowedException
     * @throws InsufficientStockException
     */
    public function deliver(FulfilOrderRequest $request, Shipment $shipment, MoveShipment $moveShipment): ShipmentResource
    {
        return new ShipmentResource($moveShipment->deliver($shipment, AccountReference::to($request->actor()))->load('order'));
    }

    /**
     * Cancel a shipment.
     *
     * Needs the orders.fulfil permission. Only before it leaves; what it held
     * can ship again.
     *
     * @throws ShipmentTransitionNotAllowedException
     */
    public function cancel(FulfilOrderRequest $request, Shipment $shipment, MoveShipment $moveShipment): ShipmentResource
    {
        return new ShipmentResource($moveShipment->cancel($shipment, AccountReference::to($request->actor()))->load('order'));
    }
}
