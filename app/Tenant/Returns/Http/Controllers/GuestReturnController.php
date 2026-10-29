<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Actions\CancelReturn;
use App\Tenant\Returns\Actions\RequestReturn;
use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Exceptions\OrderNotReturnableException;
use App\Tenant\Returns\Exceptions\ReturnPhotoRequiredException;
use App\Tenant\Returns\Exceptions\ReturnQuantityTooHighException;
use App\Tenant\Returns\Exceptions\ReturnsNotOfferedException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Exceptions\ReturnWindowClosedException;
use App\Tenant\Returns\Http\Requests\GuestReturnRequest;
use App\Tenant\Returns\Http\Requests\ListGuestReturnsRequest;
use App\Tenant\Returns\Http\Requests\ViewGuestReturnRequest;
use App\Tenant\Returns\Http\Resources\BuyerReturnResource;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * A guest's returns of their order, with its access token in the X-Order-Token header (never the URL): the same as a signed-in customer's, for that one order.
 */
final class GuestReturnController extends Controller
{
    private const array WITH = ['order', 'lines.orderLine', 'media'];

    /**
     * Ask to return units of my order.
     *
     * The same rules as a signed-in customer's request.
     *
     * @throws ReturnsNotOfferedException
     * @throws OrderNotReturnableException
     * @throws ReturnWindowClosedException
     * @throws ReturnQuantityTooHighException
     * @throws ReturnPhotoRequiredException
     * @throws UsageLimitReachedException
     */
    public function store(GuestReturnRequest $request, Order $order, RequestReturn $requestReturn): JsonResponse
    {
        $return = $requestReturn->handle(ReturnRequester::guest(), $request->guestOrder($order), $request->returnLines(), $request->reason(), $request->note(), $request->photos());

        return (new BuyerReturnResource($return->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * List my order's returns.
     *
     * Newest first.
     */
    public function index(ListGuestReturnsRequest $request, Order $order): AnonymousResourceCollection
    {
        $order = $request->guestOrder($order);

        return BuyerReturnResource::collection(OrderReturn::query()->where('order_id', $order->id)->with(self::WITH)->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show one of my order's returns.
     */
    public function show(ViewGuestReturnRequest $request, Order $order, OrderReturn $return): BuyerReturnResource
    {
        return new BuyerReturnResource($request->guestReturn($order, $return)->load(self::WITH));
    }

    /**
     * Call off one of my order's returns.
     *
     * Only while it is still requested.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    public function cancel(ViewGuestReturnRequest $request, Order $order, OrderReturn $return, CancelReturn $cancelReturn): BuyerReturnResource
    {
        return new BuyerReturnResource($cancelReturn->handle($request->guestReturn($order, $return), ReturnRequester::guest())->load(self::WITH));
    }
}
