<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Controllers;

use App\Shared\Auth\AccountReference;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Http\Controller;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Actions\ApproveReturn;
use App\Tenant\Returns\Actions\CancelReturn;
use App\Tenant\Returns\Actions\InspectReturn;
use App\Tenant\Returns\Actions\ReceiveReturn;
use App\Tenant\Returns\Actions\RejectReturn;
use App\Tenant\Returns\Actions\RequestReturn;
use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Exceptions\OrderNotReturnableException;
use App\Tenant\Returns\Exceptions\ReturnInspectionMismatchException;
use App\Tenant\Returns\Exceptions\ReturnQuantityTooHighException;
use App\Tenant\Returns\Exceptions\ReturnStaffReasonRequiredException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Http\Requests\ApproveReturnRequest;
use App\Tenant\Returns\Http\Requests\InspectReturnRequest;
use App\Tenant\Returns\Http\Requests\ListReturnsRequest;
use App\Tenant\Returns\Http\Requests\ManageReturnRequest;
use App\Tenant\Returns\Http\Requests\RejectReturnRequest;
use App\Tenant\Returns\Http\Requests\StaffReturnRequest;
use App\Tenant\Returns\Http\Requests\ViewReturnRequest;
use App\Tenant\Returns\Http\Resources\ReturnResource;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Services\ReturnPhotos;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Staff following the store's returns, starting returns for buyers, and taking each through approval, receipt and inspection (or rejecting or cancelling it).
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

    /**
     * Approve a return.
     *
     * Needs the returns.manage permission; with keep_items, the buyer keeps
     * the items and it is refunded without anything coming back, which needs
     * the payments.refund permission too. Only while it is requested.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    public function approve(ApproveReturnRequest $request, OrderReturn $return, ApproveReturn $approveReturn): ReturnResource
    {
        return new ReturnResource($approveReturn->handle($return, AccountReference::to($request->actor()), $request->keepItems())->load(self::WITH));
    }

    /**
     * Reject a return.
     *
     * Needs the returns.manage permission. While it is requested, or once
     * inspected when nothing is owed; the buyer sees the reason. Stock moved
     * at inspection stays where it went.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    public function reject(RejectReturnRequest $request, OrderReturn $return, RejectReturn $rejectReturn): ReturnResource
    {
        return new ReturnResource($rejectReturn->handle($return, $request->reason(), AccountReference::to($request->actor()))->load(self::WITH));
    }

    /**
     * Mark a return's items received.
     *
     * Needs the returns.manage permission. Only once approved, and never
     * when the buyer keeps the items. Nothing moves in stock until inspected.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    public function receive(ManageReturnRequest $request, OrderReturn $return, ReceiveReturn $receiveReturn): ReturnResource
    {
        return new ReturnResource($receiveReturn->handle($return, AccountReference::to($request->actor()))->load(self::WITH));
    }

    /**
     * Inspect a return's items.
     *
     * Needs the returns.manage permission. Only once received. Say for every
     * line how many units go back on the shelf and how many are written off
     * as damaged, adding up to its quantity, at an active location (the
     * store's default when left out).
     *
     * @throws ReturnTransitionNotAllowedException
     * @throws ReturnInspectionMismatchException
     */
    public function inspect(InspectReturnRequest $request, OrderReturn $return, InspectReturn $inspectReturn): ReturnResource
    {
        return new ReturnResource($inspectReturn->handle($return, $request->stockLocation(), $request->outcomes(), AccountReference::to($request->actor()))->load(self::WITH));
    }

    /**
     * Cancel a return.
     *
     * Needs the returns.manage permission. Before the items arrive: while it
     * is requested or approved.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    public function cancel(ManageReturnRequest $request, OrderReturn $return, CancelReturn $cancelReturn): ReturnResource
    {
        return new ReturnResource($cancelReturn->handle($return, ReturnRequester::staff($request->actor()))->load(self::WITH));
    }

    /**
     * Show a return's photo.
     *
     * Needs the returns.view permission. By its position from 1, as the JPEG,
     * PNG or WebP image it was kept as; never cached. Not found past the
     * number of photos, or once they were deleted.
     *
     * @throws NotFoundHttpException
     */
    public function photo(ViewReturnRequest $request, OrderReturn $return, int $position, FilesystemFactory $filesystems): StreamedResponse
    {
        $media = $return->getMedia(ReturnPhotos::COLLECTION)->values()->get($position - 1) ?? throw new NotFoundHttpException;

        return $filesystems->disk($media->disk)->response($media->getPathRelativeToRoot(), $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
