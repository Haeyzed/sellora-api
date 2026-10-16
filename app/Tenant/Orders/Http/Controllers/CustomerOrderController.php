<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Exceptions\OrderAlreadyPaidException;
use App\Tenant\Orders\Exceptions\OrderCannotBeCancelledException;
use App\Tenant\Orders\Http\Requests\CustomerOrderRequest;
use App\Tenant\Orders\Http\Requests\ListCustomerOrdersRequest;
use App\Tenant\Orders\Http\Resources\CustomerOrderResource;
use App\Tenant\Orders\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A signed-in customer's own orders. Anyone else's order is not found.
 */
final class CustomerOrderController extends Controller
{
    /** What showing an order to its customer needs, loaded at once. */
    private const array DETAIL = ['lines', 'shippingLine', 'addresses', 'shipments'];

    /**
     * List your orders.
     *
     * Newest first, guest orders placed with your verified email included.
     */
    public function index(ListCustomerOrdersRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()->with(self::DETAIL)->where('customer_id', $request->customer()->id)->orderByDesc('placed_at')->orderByDesc('id');

        return CustomerOrderResource::collection($orders->cursorPaginate($request->perPage()));
    }

    /**
     * Show one of your orders.
     */
    public function show(CustomerOrderRequest $request, Order $order): CustomerOrderResource
    {
        return new CustomerOrderResource($request->ownOrder($order)->load(self::DETAIL));
    }

    /**
     * Cancel one of your orders.
     *
     * Only before you pay and before anything ships. A paid order can be
     * cancelled only by the store; contact it.
     *
     * @throws OrderAlreadyPaidException
     * @throws OrderCannotBeCancelledException
     */
    public function cancel(CustomerOrderRequest $request, Order $order, CancelOrder $cancelOrder): CustomerOrderResource
    {
        return new CustomerOrderResource($cancelOrder->byCustomer($request->customer(), $request->ownOrder($order))->load(self::DETAIL));
    }
}
