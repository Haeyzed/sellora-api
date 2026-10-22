<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Checkout\Actions\FindCheckout;
use App\Tenant\Checkout\Actions\StartCheckout;
use App\Tenant\Checkout\Actions\UpdateCheckout;
use App\Tenant\Checkout\Exceptions\CheckoutCannotStartException;
use App\Tenant\Checkout\Exceptions\CheckoutNotFoundException;
use App\Tenant\Checkout\Exceptions\GuestCheckoutNotAllowedException;
use App\Tenant\Checkout\Http\Requests\UpdateCheckoutRequest;
use App\Tenant\Checkout\Http\Resources\CheckoutResource;
use App\Tenant\Checkout\Services\CheckoutQuotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Checking out the shopper's cart (Step 16): a signed-in customer's (bearer token) or a guest's (X-Cart-Token header), while the store allows guest checkout.
 */
final class CheckoutController extends Controller
{
    /**
     * Start checking out.
     *
     * Starts checking out the cart, or picks up the checkout already started
     * for it, and returns its quote. Nothing is held yet: stock and promotion
     * uses are held only when the order is placed. A signed-in customer always
     * uses their account's email.
     *
     * @throws GuestCheckoutNotAllowedException
     * @throws CartNotFoundException
     * @throws CheckoutCannotStartException
     */
    public function store(Request $request, StartCheckout $startCheckout): JsonResponse
    {
        return (new CheckoutResource($startCheckout->handle(Shopper::fromRequest($request))))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show my checkout.
     *
     * Its quote as it stands now: the same version while nothing charged has
     * changed and it hasn't expired, otherwise a new one.
     *
     * @throws GuestCheckoutNotAllowedException
     * @throws CheckoutNotFoundException
     */
    public function show(Request $request, FindCheckout $findCheckout, CheckoutQuotes $checkoutQuotes): CheckoutResource
    {
        return new CheckoutResource($checkoutQuotes->refresh($findCheckout->handle(Shopper::fromRequest($request))));
    }

    /**
     * Change my checkout.
     *
     * Saves what is sent (contact details, addresses, the way to ship and to
     * pay, a tax ID, marketing consent, a note) and returns the quote it now
     * comes to.
     *
     * @throws GuestCheckoutNotAllowedException
     * @throws CheckoutNotFoundException
     */
    public function update(UpdateCheckoutRequest $request, FindCheckout $findCheckout, UpdateCheckout $updateCheckout): CheckoutResource
    {
        return new CheckoutResource($updateCheckout->handle($findCheckout->handle(Shopper::fromRequest($request)), $request->changes()));
    }
}
