<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Checkout\Actions\PlaceCheckoutOrder;
use App\Tenant\Checkout\Exceptions\CheckoutNotFoundException;
use App\Tenant\Checkout\Exceptions\CheckoutNotReadyException;
use App\Tenant\Checkout\Exceptions\CheckoutQuoteChangedException;
use App\Tenant\Checkout\Exceptions\GuestCheckoutNotAllowedException;
use App\Tenant\Checkout\Exceptions\ReturnUrlRequiredException;
use App\Tenant\Checkout\Http\Requests\PlaceCheckoutOrderRequest;
use App\Tenant\Checkout\Http\Resources\CheckoutResource;
use App\Tenant\Checkout\Http\Resources\PlacedOrderResource;
use App\Tenant\Orders\Models\OrderAccessToken;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Placing the order for a checkout (Step 16): a signed-in customer's (bearer token) or a guest's (X-Cart-Token header). Needs an Idempotency-Key header.
 */
final class CheckoutOrderController extends Controller
{
    /**
     * Place the order.
     *
     * Places the order for the quote version the shopper confirmed, all or
     * nothing: its stock and promotion uses are held for it, and the cart is
     * emptied. When the quote is no longer the one confirmed (it expired, or a
     * price, stock, a promotion, shipping, tax or a payment method changed),
     * nothing is placed: 409 checkout_quote_changed, with the new quote as
     * `data`, to show and confirm. 409 checkout_not_ready, with the quote,
     * while it has issues. A guest gets their order's access token in the
     * X-Order-Token response header: keep it to see, pay for or cancel the
     * order. Retrying with the same Idempotency-Key returns the same answer
     * with a fresh token, which replaces the earlier one. Placing again with
     * another key returns the same order (200), without a token.
     *
     * @throws GuestCheckoutNotAllowedException
     * @throws CheckoutNotFoundException
     * @throws ReturnUrlRequiredException
     */
    #[HeaderParameter('Idempotency-Key', 'A new key for each order, 8 to 128 letters, digits, - or _. Sending the same request again with it returns the first answer instead of placing a second order.', required: true, type: 'string', example: 'order-2f9c1a7e')]
    public function store(PlaceCheckoutOrderRequest $request, PlaceCheckoutOrder $placeCheckoutOrder): JsonResponse
    {
        try {
            $placed = $placeCheckoutOrder->handle(
                Shopper::fromRequest($request),
                $request->string('checkout')->value(),
                $request->integer('version'),
                $request->filled('return_url') ? $request->string('return_url')->value() : null,
                (string) $request->headers->get('Idempotency-Key'),
            );
        } catch (CheckoutQuoteChangedException|CheckoutNotReadyException $exception) {
            return (new CheckoutResource($exception->quote))
                ->additional(['code' => (string) $exception->errorCode(), 'message' => (string) $exception->translatedMessage()])
                ->response()
                ->setStatusCode(Response::HTTP_CONFLICT);
        }

        $headers = $placed->orderToken === null ? [] : [OrderAccessToken::HEADER => $placed->orderToken];

        if (! $placed->isNew) {
            return (new PlacedOrderResource($placed))->response()->withHeaders($headers);
        }

        return (new PlacedOrderResource($placed))->response()->setStatusCode(Response::HTTP_CREATED)->withHeaders($headers);
    }
}
