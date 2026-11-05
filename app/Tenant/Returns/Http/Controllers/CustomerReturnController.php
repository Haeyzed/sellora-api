<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Actions\CancelReturn;
use App\Tenant\Returns\Actions\ChooseRefundDestination;
use App\Tenant\Returns\Actions\RequestReturn;
use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Exceptions\OrderNotReturnableException;
use App\Tenant\Returns\Exceptions\RefundDestinationChoiceClosedException;
use App\Tenant\Returns\Exceptions\RefundDestinationNotOfferedException;
use App\Tenant\Returns\Exceptions\ReturnPhotoRequiredException;
use App\Tenant\Returns\Exceptions\ReturnQuantityTooHighException;
use App\Tenant\Returns\Exceptions\ReturnsNotOfferedException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Exceptions\ReturnWindowClosedException;
use App\Tenant\Returns\Http\Requests\ChooseRefundDestinationRequest;
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
    private const array WITH = ['order', 'lines.orderLine', 'lines.replacementVariant.product', 'media', 'refundChoices', 'exchanges.order'];

    /**
     * Ask to return units of an order.
     *
     * Only for delivered units within the store's return window, while the
     * store takes buyers' requests, and never more than is left of a line
     * (units already in a return or refunded can't be returned again). Send
     * multipart form data to add photos; when the store requires them, damage
     * or the wrong item needs at least one. `refund_destination` says where
     * the refund goes, from the order's refund destinations; left out, it
     * goes back to how the customer paid.
     *
     * @throws RefundDestinationNotOfferedException
     * @throws ReturnsNotOfferedException
     * @throws OrderNotReturnableException
     * @throws ReturnWindowClosedException
     * @throws ReturnQuantityTooHighException
     * @throws ReturnPhotoRequiredException
     * @throws UsageLimitReachedException
     */
    public function store(CustomerReturnRequest $request, Order $order, RequestReturn $requestReturn): JsonResponse
    {
        $return = $requestReturn->handle(
            ReturnRequester::customer($request->customer()),
            $request->ownOrder($order),
            $request->returnLines(),
            $request->reason(),
            $request->note(),
            $request->photos(),
            refundDestination: $request->refundDestination(),
            resolution: $request->resolution(),
            replacements: $request->replacements(),
        );

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
     * Choose where my return's refund goes.
     *
     * From the return's refund destinations: back to how I paid, or a
     * destination the store offers me, such as store credit. Only before its
     * refund starts, and again only if that refund definitely failed; never
     * while a refund may be on its way (409). Only covers what was paid with
     * real money: what a store payment method (such as a gift card) paid goes
     * back to it.
     *
     * @throws RefundDestinationChoiceClosedException
     * @throws RefundDestinationNotOfferedException
     */
    public function chooseRefundDestination(ChooseRefundDestinationRequest $request, OrderReturn $return, ChooseRefundDestination $chooseRefundDestination): BuyerReturnResource
    {
        $chooseRefundDestination->handle($request->ownReturn($return), $request->destination(), $request->customer());

        return new BuyerReturnResource($return->refresh()->load(self::WITH));
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
