<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Controllers;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Actions\RequestReturn;
use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Exceptions\OrderNotReturnableException;
use App\Tenant\Returns\Exceptions\ReturnQuantityTooHighException;
use App\Tenant\Returns\Exceptions\ReturnStaffReasonRequiredException;
use App\Tenant\Returns\Http\Requests\ListReturnsRequest;
use App\Tenant\Returns\Http\Requests\StaffReturnRequest;
use App\Tenant\Returns\Http\Requests\ViewReturnRequest;
use App\Tenant\Returns\Http\Resources\ReturnResource;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Staff following the store's returns, and starting returns for buyers.
 */
final class ReturnController extends Controller
{
    private const array WITH = ['order', 'lines.orderLine', 'stockLocation', 'media'];

    /**
     * List returns.
     *
     * Needs the returns.view permission. Newest first.
     */
    public function index(ListReturnsRequest $request): AnonymousResourceCollection
    {
        $query = OrderReturn::query()->with(self::WITH);
        $request->applyFilters($query);

        return ReturnResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show a return.
     *
     * Needs the returns.view permission. With every change of its status.
     */
    public function show(ViewReturnRequest $request, OrderReturn $return): ReturnResource
    {
        return new ReturnResource($return->load([...self::WITH, 'statusChanges']));
    }

    /**
     * Start a return for the buyer.
     *
     * Needs the returns.manage permission. Staff can start a return whatever
     * the store's settings, and for units delivered before the window
     * (outside_window_reason) or shipped and never marked delivered
     * (undelivered_reason), saying why. Never more than is left of a line.
     *
     * @throws OrderNotReturnableException
     * @throws ReturnQuantityTooHighException
     * @throws ReturnStaffReasonRequiredException
     * @throws UsageLimitReachedException
     */
    public function store(StaffReturnRequest $request, Order $order, RequestReturn $requestReturn): JsonResponse
    {
        $return = $requestReturn->handle(
            ReturnRequester::staff($request->actor()),
            $order,
            $request->returnLines(),
            $request->reason(),
            $request->note(),
            $request->photos(),
            $request->outsideWindowReason(),
            $request->undeliveredReason(),
        );

        return (new ReturnResource($return->load(self::WITH)))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
