<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Exceptions\OrderNotRefundableException;
use App\Tenant\Payments\Exceptions\PartialRefundNotSupportedException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Exceptions\RefundExceedsOverpaymentException;
use App\Tenant\Payments\Exceptions\RefundExceedsRefundableException;
use App\Tenant\Payments\Exceptions\RefundExceedsSaleException;
use App\Tenant\Payments\Exceptions\RefundInvalidException;
use App\Tenant\Payments\Http\Requests\ListRefundsRequest;
use App\Tenant\Payments\Http\Requests\RefundOrderRequest;
use App\Tenant\Payments\Http\Requests\ViewPaymentsRequest;
use App\Tenant\Payments\Http\Resources\RefundResource;
use App\Tenant\Payments\Models\Refund;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Staff giving money back on orders, and following where it stands.
 */
final class RefundController extends Controller
{
    private const array WITH = ['order', 'lines.orderLine', 'paymentRefunds.payment'];

    /**
     * List refunds.
     *
     * Needs the payments.view permission. Newest first.
     */
    public function index(ListRefundsRequest $request): AnonymousResourceCollection
    {
        return RefundResource::collection(Refund::query()->with(self::WITH)->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Show a refund.
     *
     * Needs the payments.view permission.
     */
    public function show(ViewPaymentsRequest $request, Refund $refund): RefundResource
    {
        return new RefundResource($refund->load(self::WITH));
    }

    /**
     * Refund an order.
     *
     * Needs the payments.refund permission and an Idempotency-Key. Gives back
     * an amount, or lines and shipping at what the buyer paid for them after
     * discounts, tax included; never stock (Returns does that). Never more
     * than was paid less what is already refunded or on its way back. Each
     * part goes back the way its payment came: through its gateway (pending
     * until the gateway confirms), or recorded as paid back by hand. An open
     * order must be paid in full; a cancelled one gives back what it holds.
     * Each refund has a purpose: one that reduces the sale gets a credit note
     * and never takes back more than the order's total; money paid over the
     * total goes back as an overpayment.
     *
     * @throws OrderNotRefundableException
     * @throws RefundInvalidException
     * @throws RefundExceedsRefundableException
     * @throws RefundExceedsSaleException
     * @throws RefundExceedsOverpaymentException
     * @throws PartialRefundNotSupportedException
     * @throws PaymentGatewayNotConnectedException
     */
    #[HeaderParameter('Idempotency-Key', 'A new key for each refund, 8 to 128 letters, digits, - or _. Sending the same request again with it returns the first answer instead of giving money back twice.', required: true, type: 'string', example: 'refund-2f9c1a7e')]
    public function store(RefundOrderRequest $request, Order $order, RefundOrder $refundOrder): JsonResponse
    {
        $refund = $refundOrder->handle($request->actor(), $order, $request->refundRequest($order));

        return (new RefundResource($refund->load(self::WITH)))->response()->setStatusCode(201);
    }
}
