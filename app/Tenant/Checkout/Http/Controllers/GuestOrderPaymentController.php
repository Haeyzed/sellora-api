<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Checkout\Actions\PayOrderAgain;
use App\Tenant\Checkout\Exceptions\ReturnUrlRequiredException;
use App\Tenant\Checkout\Http\Requests\PayGuestOrderAgainRequest;
use App\Tenant\Checkout\Http\Resources\OrderPaymentResource;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Exceptions\OrderNotPayableException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotAvailableException;
use App\Tenant\Payments\Exceptions\PaymentInProgressException;
use App\Tenant\Payments\Exceptions\PaymentMethodNotAvailableException;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A guest paying again for their order still waiting for payment (Step 16), with its access token in the X-Order-Token header. Needs an Idempotency-Key header.
 */
final class GuestOrderPaymentController extends Controller
{
    /**
     * Pay for your order again.
     *
     * Starts a new payment of what is left, with a gateway ("gateway:{key}",
     * then send the buyer to redirect_url) or a method paid by hand. 409 while
     * an earlier gateway payment may still go through; 422 once the order
     * can no longer be paid (cancelled, paid, or past its deadline).
     *
     * @throws ModelNotFoundException When the token is missing, expired or not this order's.
     * @throws ReturnUrlRequiredException
     * @throws PaymentGatewayNotAvailableException
     * @throws PaymentMethodNotAvailableException
     * @throws OrderNotPayableException
     * @throws PaymentInProgressException
     */
    #[HeaderParameter('Idempotency-Key', 'A new key for each payment, 8 to 128 letters, digits, - or _. Sending the same request again with it returns the first answer instead of starting a second payment.', required: true, type: 'string', example: 'pay-2f9c1a7e')]
    public function store(PayGuestOrderAgainRequest $request, Order $order, PayOrderAgain $payOrderAgain): JsonResponse
    {
        $payment = $payOrderAgain->handle(
            $request->guestOrder($order),
            $request->string('payment_method')->value(),
            $request->filled('return_url') ? $request->string('return_url')->value() : null,
            (string) $request->headers->get('Idempotency-Key'),
        );

        return (new OrderPaymentResource($payment))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
