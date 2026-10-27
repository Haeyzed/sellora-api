<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Auth\AccountReference;
use App\Shared\Http\Controller;
use App\Tenant\Delivery\Actions\AssignDriver;
use App\Tenant\Delivery\Actions\CancelDelivery;
use App\Tenant\Delivery\Actions\MoveDelivery;
use App\Tenant\Delivery\Actions\ReassignDelivery;
use App\Tenant\Delivery\Actions\ResolveFailedDelivery;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Exceptions\DeliveryTransitionNotAllowedException;
use App\Tenant\Delivery\Exceptions\DriverDeactivatedException;
use App\Tenant\Delivery\Exceptions\DriverErasedException;
use App\Tenant\Delivery\Exceptions\ShipmentCannotBeAssignedException;
use App\Tenant\Delivery\Http\Requests\ChooseDriverRequest;
use App\Tenant\Delivery\Http\Requests\ListDeliveriesRequest;
use App\Tenant\Delivery\Http\Requests\ManageDeliveryRequest;
use App\Tenant\Delivery\Http\Requests\MoveDeliveryRequest;
use App\Tenant\Delivery\Http\Requests\ViewDeliveryRequest;
use App\Tenant\Delivery\Http\Resources\DeliveryResource;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Exceptions\ShipmentTransitionNotAllowedException;
use App\Tenant\Orders\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Staff running the store's own deliveries: giving shipments to drivers, and following, moving or resolving their deliveries.
 */
final class DeliveryController extends Controller
{
    private const array WITH = ['shipment.order', 'driver'];

    /**
     * List deliveries.
     *
     * Needs the delivery.view permission. Newest first.
     */
    public function index(ListDeliveriesRequest $request): AnonymousResourceCollection
    {
        $query = Delivery::query()->with(self::WITH);
        $request->applyFilters($query);

        return DeliveryResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show a delivery.
     *
     * Needs the delivery.view permission. With every change of its status.
     */
    public function show(ViewDeliveryRequest $request, Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($delivery->load([...self::WITH, 'statusChanges']));
    }

    /**
     * Assign a driver to a shipment.
     *
     * Needs the delivery.manage permission. Only for one of the store's own
     * deliveries that hasn't left, without a delivery already under way, and
     * an active driver. Staff can no longer mark it shipped or delivered
     * themselves: the driver's delivery moves it along.
     *
     * @throws ShipmentCannotBeAssignedException
     * @throws DriverDeactivatedException
     * @throws DriverErasedException
     */
    public function store(ChooseDriverRequest $request, Shipment $shipment, AssignDriver $assignDriver): JsonResponse
    {
        $delivery = $assignDriver->handle($request->actor(), $shipment, $request->chosenDriver());

        return (new DeliveryResource($delivery->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Give a delivery to another driver.
     *
     * Needs the delivery.manage permission. Only before it is picked up: it is
     * cancelled, and the other driver gets a new delivery, which is returned.
     *
     * @throws DeliveryTransitionNotAllowedException
     * @throws ShipmentCannotBeAssignedException
     * @throws DriverDeactivatedException
     * @throws DriverErasedException
     */
    public function reassign(ChooseDriverRequest $request, Delivery $delivery, ReassignDelivery $reassignDelivery): JsonResponse
    {
        $assigned = $reassignDelivery->handle($request->actor(), $delivery, $request->chosenDriver());

        return (new DeliveryResource($assigned->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Cancel a delivery.
     *
     * Needs the delivery.manage permission. Only before it is picked up; the
     * shipment can then be given to a driver again.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function cancel(ManageDeliveryRequest $request, Delivery $delivery, CancelDelivery $cancelDelivery): DeliveryResource
    {
        return new DeliveryResource($cancelDelivery->byStaff($request->actor(), $delivery)->load(self::WITH));
    }

    /**
     * Move a delivery along for its driver.
     *
     * Needs the delivery.manage permission. For a driver who can't use the
     * app, such as one whose phone died: picked up, on the way, delivered
     * (with who took the goods) or failed (with why). Recorded as done by
     * the staff member.
     *
     * @throws DeliveryTransitionNotAllowedException
     * @throws ShipmentTransitionNotAllowedException
     * @throws InsufficientStockException
     */
    public function move(MoveDeliveryRequest $request, Delivery $delivery, MoveDelivery $moveDelivery): DeliveryResource
    {
        $by = AccountReference::to($request->actor());
        $moved = match ($request->nextStatus()) {
            DeliveryStatus::PickedUp => $moveDelivery->pickUp($delivery, $by),
            DeliveryStatus::OnTheWay => $moveDelivery->onTheWay($delivery, $by),
            DeliveryStatus::Delivered => $moveDelivery->deliver($delivery, $by, $request->recipientName()),
            default => $moveDelivery->fail($delivery, $by, $request->failureReason(), $request->failureNote()),
        };

        return new DeliveryResource($moved->load(self::WITH));
    }

    /**
     * Send a failed delivery out again.
     *
     * Needs the delivery.manage permission. The same driver, who still has
     * the goods, tries again: a new delivery, which is returned, starting
     * picked up.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function retry(ManageDeliveryRequest $request, Delivery $delivery, ResolveFailedDelivery $resolveFailedDelivery): JsonResponse
    {
        $retry = $resolveFailedDelivery->retry($request->actor(), $delivery);

        return (new DeliveryResource($retry->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Take back a failed delivery's goods.
     *
     * Needs the delivery.manage permission. The driver brought them back to
     * the store: the shipment is returned and its stock is back on the shelf,
     * held for the order, which can be shipped again or cancelled. An unpaid
     * order's payment deadline starts again from now.
     *
     * @throws DeliveryTransitionNotAllowedException
     */
    public function returnToStore(ManageDeliveryRequest $request, Delivery $delivery, ResolveFailedDelivery $resolveFailedDelivery): DeliveryResource
    {
        return new DeliveryResource($resolveFailedDelivery->returnToStore($request->actor(), $delivery)->load(self::WITH));
    }
}
