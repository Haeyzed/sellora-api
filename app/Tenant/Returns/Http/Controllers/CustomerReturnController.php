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
use App\Tenant\Returns\Http\Requests\CustomerReturnRequest;
use App\Tenant\Returns\Http\Requests\ListCustomerReturnsRequest;
use App\Tenant\Returns\Http\Requests\ViewCustomerReturnRequest;
use App\Tenant\Returns\Http\Resources\BuyerReturnResource;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * A signed-in customer's returns: asking to send units of their orders back, following them, and calling one off while it is still requested.
 */
final class CustomerReturnController extends Controller
{
    private const array WITH = ['order', 'lines.orderLine', 'media'];

    /**
     * Ask to return units of an order.
     *
     * Only for delivered units within the store's return window, while the
     * store takes buyers' requests, and never more than is left of a line
     * (units already in a return or refunded can't be returned again). Send
     * multipart form data to add photos; when the store requires them, damage
     * or the wrong item needs at least one.
     *
     * @throws ReturnsNotOfferedException
     * @throws OrderNotReturnableException
     * @throws ReturnWindowClosedException
     * @throws ReturnQuantityTooHighException
     * @throws ReturnPhotoRequiredException
     * @throws UsageLimitReachedException
     */
    public function store(CustomerReturnRequest $request, Order $order, RequestReturn $requestReturn): JsonResponse
    {
        $return = $requestReturn->handle(ReturnRequester::customer($request->customer()), $request->ownOrder($order), $request->returnLines(), $request->reason(), $request->note(), $request->photos());

        return (new BuyerReturnResource($return->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * List my returns.
     *
     * Of every order of mine, newest first.
     */
    public function index(ListCustomerReturnsRequest $request): AnonymousResourceCollection
    {
        return BuyerReturnResource::collection($request->ownReturns()->with(self::WITH)->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show one of my returns.
     */
    public function show(ViewCustomerReturnRequest $request, OrderReturn $return): BuyerReturnResource
    {
        return new BuyerReturnResource($request->ownReturn($return)->load(self::WITH));
    }

    /**
     * Call off one of my returns.
     *
     * Only while it is still requested; once the store approved it, ask the store.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    public function cancel(ViewCustomerReturnRequest $request, OrderReturn $return, CancelReturn $cancelReturn): BuyerReturnResource
    {
        return new BuyerReturnResource($cancelReturn->handle($request->ownReturn($return), ReturnRequester::customer($request->customer()))->load(self::WITH));
    }
}
