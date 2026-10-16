<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\ChangeOrderTags;
use App\Tenant\Orders\Actions\CorrectOrderContact;
use App\Tenant\Orders\Actions\CorrectShippingAddress;
use App\Tenant\Orders\Exceptions\OrderAddressCannotBeCorrectedException;
use App\Tenant\Orders\Exceptions\OrderAddressRegionChangedException;
use App\Tenant\Orders\Exceptions\OrderCannotBeCancelledException;
use App\Tenant\Orders\Http\Requests\ChangeOrderTagsRequest;
use App\Tenant\Orders\Http\Requests\CorrectOrderContactRequest;
use App\Tenant\Orders\Http\Requests\CorrectShippingAddressRequest;
use App\Tenant\Orders\Http\Requests\ListOrdersRequest;
use App\Tenant\Orders\Http\Requests\ManageOrderRequest;
use App\Tenant\Orders\Http\Requests\ViewOrderRequest;
use App\Tenant\Orders\Http\Resources\OrderEventResource;
use App\Tenant\Orders\Http\Resources\OrderResource;
use App\Tenant\Orders\Http\Resources\OrderSummaryResource;
use App\Tenant\Orders\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Staff working with the store's orders. Orders are found by their ID, never by their number, which is only for people to read.
 */
final class StaffOrderController extends Controller
{
    /** What showing an order needs, loaded at once. */
    public const array DETAIL = ['customer', 'lines.product', 'lines.variant', 'lines.discounts', 'lines.taxes', 'discounts', 'taxes', 'shippingLine.method', 'addresses', 'tags', 'contactCorrections'];

    /** What listing orders needs, loaded at once for the page. */
    public const array SUMMARY = ['customer', 'tags', 'contactCorrections'];

    /**
     * List orders.
     *
     * Needs the orders.view permission. Newest first. Search by order number
     * or by a buyer's whole email; narrow by status, date, customer, currency,
     * shipping method, promotion or tag.
     */
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $query = Order::query()->with(self::SUMMARY);
        $request->applyFilters($query);

        return OrderSummaryResource::collection($query->orderByDesc('placed_at')->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * List a customer's orders.
     *
     * Needs the orders.view permission. Newest first. Includes guest orders
     * that joined the account when its email was verified.
     */
    public function forCustomer(ListOrdersRequest $request, Customer $customer): AnonymousResourceCollection
    {
        $query = Order::query()->with(self::SUMMARY)->where('customer_id', $customer->id);
        $request->applyFilters($query);

        return OrderSummaryResource::collection($query->orderByDesc('placed_at')->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show an order.
     *
     * Needs the orders.view permission. Everything as placed, with the
     * contact details and shipping address as they stand after corrections.
     */
    public function show(ViewOrderRequest $request, Order $order): OrderResource
    {
        return new OrderResource($order->load(self::DETAIL));
    }

    /**
     * Show an order's history.
     *
     * Needs the orders.view permission. Oldest first: placement, payment,
     * cancellation, corrections, tags and shipments, with who did them.
     */
    public function events(ViewOrderRequest $request, Order $order): AnonymousResourceCollection
    {
        return OrderEventResource::collection($order->events()->get());
    }

    /**
     * Cancel an order.
     *
     * Needs the orders.manage permission. Only an open order that hasn't
     * started to ship. Its stock and promotion uses are given back; a paid
     * order is then marked as owing a refund, which Payments makes.
     *
     * @throws OrderCannotBeCancelledException
     */
    public function cancel(ManageOrderRequest $request, Order $order, CancelOrder $cancelOrder): OrderResource
    {
        return new OrderResource($cancelOrder->byStaff($request->actor(), $order)->load(self::DETAIL));
    }

    /**
     * Change an order's tags.
     *
     * Needs the orders.manage permission. The list replaces the current one.
     */
    public function tags(ChangeOrderTagsRequest $request, Order $order, ChangeOrderTags $changeOrderTags): OrderResource
    {
        return new OrderResource($changeOrderTags->handle($request->actor(), $order, $request->tags())->load(self::DETAIL));
    }

    /**
     * Correct an order's contact details.
     *
     * Needs the orders.manage permission. Kept as a new version beside the
     * details given at checkout, which never change. A corrected email is
     * where the store writes from now on; it never links the order to an
     * account and never changes whose first order it was.
     */
    public function contact(CorrectOrderContactRequest $request, Order $order, CorrectOrderContact $correctOrderContact): OrderResource
    {
        return new OrderResource($correctOrderContact->handle($request->actor(), $order, $request->email(), $request->name(), $request->phone())->load(self::DETAIL));
    }

    /**
     * Correct an order's shipping address.
     *
     * Needs the orders.manage permission. Only while the order is open and
     * before anything ships, and only within the same country and state, so
     * its tax and shipping price still hold. Kept as a new version.
     *
     * @throws OrderAddressCannotBeCorrectedException
     * @throws OrderAddressRegionChangedException
     */
    public function shippingAddress(CorrectShippingAddressRequest $request, Order $order, CorrectShippingAddress $correctShippingAddress): OrderResource
    {
        return new OrderResource($correctShippingAddress->handle($request->actor(), $order, $request->address())->load(self::DETAIL));
    }
}
