<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Exceptions\OrderAlreadyPaidException;
use App\Tenant\Orders\Exceptions\OrderCannotBeCancelledException;
use App\Tenant\Orders\Http\Requests\GuestOrderRequest;
use App\Tenant\Orders\Http\Resources\CustomerOrderResource;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A guest's order, reached with the access token given once when it was placed, in the X-Order-Token header (never the URL). Without the order's own unexpired token it is not found.
 */
final class GuestOrderController extends Controller
{
    /** What showing an order to its buyer needs, loaded at once. */
    private const array DETAIL = ['lines', 'shippingLine', 'addresses', 'shipments'];

    /**
     * Show your order.
     *
     * @throws ModelNotFoundException When the token is missing, expired or not this order's.
     */
    public function show(GuestOrderRequest $request, Order $order): CustomerOrderResource
    {
        return new CustomerOrderResource($request->guestOrder($order)->load(self::DETAIL));
    }

    /**
     * Cancel your order.
     *
     * Only before you pay and before anything ships. A paid order can be
     * cancelled only by the store; contact it.
     *
     * @throws ModelNotFoundException When the token is missing, expired or not this order's.
     * @throws OrderAlreadyPaidException
     * @throws OrderCannotBeCancelledException
     */
    public function cancel(GuestOrderRequest $request, Order $order, CancelOrder $cancelOrder): CustomerOrderResource
    {
        return new CustomerOrderResource($cancelOrder->byGuest($request->guestOrder($order))->load(self::DETAIL));
    }
}
