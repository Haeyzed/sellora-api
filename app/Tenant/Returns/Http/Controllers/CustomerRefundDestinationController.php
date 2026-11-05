<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\FindRefundDestinations;
use App\Tenant\Returns\Http\Requests\ViewCustomerOrderRequest;
use App\Tenant\Returns\Http\Requests\ViewCustomerReturnRequest;
use App\Tenant\Returns\Http\Resources\RefundDestinationResource;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Where a signed-in customer may choose to have a return's refund go: back to how they paid, always, and any destination a module offers them, such as store credit.
 */
final class CustomerRefundDestinationController extends Controller
{
    /**
     * List where an order's return refunds can go.
     *
     * For choosing when asking to return units of the order. Back to how the
     * customer paid comes first and is always offered; a module's destination
     * (such as store credit) only while the store uses that module. It only
     * ever covers what was paid with real money (card, transfer, cash): what
     * a module's payment method paid goes back to it. Names are in the
     * language asked for.
     */
    public function forOrder(ViewCustomerOrderRequest $request, Order $order, FindRefundDestinations $findRefundDestinations): AnonymousResourceCollection
    {
        return RefundDestinationResource::collection($findRefundDestinations->handle($request->ownOrder($order), $request->customer()));
    }

    /**
     * List where a return's refund can go.
     *
     * As for an order: back to how the customer paid, always, and any
     * destination a module offers them now.
     */
    public function forReturn(ViewCustomerReturnRequest $request, OrderReturn $return, FindRefundDestinations $findRefundDestinations): AnonymousResourceCollection
    {
        return RefundDestinationResource::collection($findRefundDestinations->handle($request->ownReturn($return)->order, $request->customer()));
    }
}
