<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Actions\AcknowledgePriceChanges;
use App\Tenant\Cart\Actions\ChangeCartSettings;
use App\Tenant\Cart\Actions\CreateCart;
use App\Tenant\Cart\Actions\FindCart;
use App\Tenant\Cart\Actions\PriceCart;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Exceptions\ShippingMethodNotOfferedException;
use App\Tenant\Cart\Exceptions\StateNotInCountryException;
use App\Tenant\Cart\Http\CartHttp;
use App\Tenant\Cart\Http\Requests\ChangeCartSettingsRequest;
use App\Tenant\Cart\Http\Resources\CartResource;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Shipping\Http\Resources\ShippingQuoteResource;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shopper's cart, for storefront apps: a signed-in customer's (with their bearer token) or a guest's (with the X-Cart-Token header).
 *
 * Every answer is the whole cart priced as it stands now, with its version
 * as the ETag; changes that replace something send it back in If-Match.
 * Tax in a cart is an estimate until checkout has the delivery address.
 */
final class CartController extends Controller
{
    public function __construct(
        private readonly PriceCart $priceCart,
    ) {}

    /**
     * Create a cart.
     *
     * For a guest, a new cart and its token: the token is in this response
     * only, so keep it and send it in the X-Cart-Token header from now on.
     * For a signed-in customer, their own cart (each has one), created the
     * first time: 201 when created, 200 when it already existed. Limited per
     * IP address, with a high ceiling, because a guest has nothing else to be
     * told apart by yet.
     */
    public function store(Request $request, CreateCart $createCart): JsonResponse|CartResource
    {
        $new = $createCart->handle(CartHttp::shopper($request));
        $resource = new CartResource($this->priceCart->handle($new->cart), $new->token);

        if ($new->created) {
            return $resource->response()->setStatusCode(Response::HTTP_CREATED);
        }

        return $resource;
    }

    /**
     * Get my cart.
     *
     * Priced now, in the currency chosen while the store offers it. A guest
     * without a cart, or with a token that finds none, gets 404
     * cart_not_found: create one first.
     *
     * @throws CartNotFoundException
     */
    public function show(Request $request, FindCart $findCart): CartResource
    {
        return new CartResource($this->priceCart->handle($findCart->handle(CartHttp::shopper($request))));
    }

    /**
     * List my cart's shipping options.
     *
     * The ways the cart can be shipped to the country (and state) the
     * shopper gave, or the store's own, and what each costs: the zone's
     * methods by position, then pickups in that country. Priced by the
     * weight and value, after discounts, of the lines that can be bought and
     * ship; a carrier's live rate is calculated at checkout, from the full
     * address. "required" is false when nothing in the cart ships. Not paged.
     *
     * @throws CartNotFoundException
     */
    public function shippingOptions(Request $request, FindCart $findCart): ShippingQuoteResource
    {
        return new ShippingQuoteResource($this->priceCart->handle($findCart->handle(CartHttp::shopper($request)))->shipping->quote);
    }

    /**
     * Change my cart's currency, place or shipping method.
     *
     * The currency must be one the store offers now (null for the store's
     * own). The country, and a state of it, are only for the tax and
     * shipping estimate, until checkout has the delivery address (null for
     * the store's own country; changing the country clears the state). The
     * shipping method must be one GET /cart/shipping-options offers (null for
     * none).
     *
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     * @throws CurrencyNotOfferedException
     * @throws StateNotInCountryException
     * @throws ShippingMethodNotOfferedException
     */
    #[HeaderParameter('If-Match', 'The cart version (ETag) this change was made from.', type: 'string', example: '"7"')]
    public function update(ChangeCartSettingsRequest $request, ChangeCartSettings $changeCartSettings): CartResource
    {
        $cart = $changeCartSettings->handle(CartHttp::shopper($request), $request->settings(), CartHttp::expectedVersion($request));

        return new CartResource($this->priceCart->handle($cart));
    }

    /**
     * Acknowledge price changes.
     *
     * Records that the shopper has seen the prices as they are, so the
     * changes flagged on lines (price_changed) stop being flagged. A price
     * that moves later is flagged again.
     *
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    #[HeaderParameter('If-Match', 'The cart version (ETag) the shopper saw the prices in.', type: 'string', example: '"7"')]
    public function acknowledgePriceChanges(Request $request, AcknowledgePriceChanges $acknowledgePriceChanges): CartResource
    {
        $cart = $acknowledgePriceChanges->handle(CartHttp::shopper($request), CartHttp::expectedVersion($request));

        return new CartResource($this->priceCart->handle($cart));
    }
}
