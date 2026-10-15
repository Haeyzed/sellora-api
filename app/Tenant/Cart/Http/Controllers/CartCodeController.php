<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Actions\EnterPromotionCode;
use App\Tenant\Cart\Actions\PriceCart;
use App\Tenant\Cart\Actions\RemovePromotionCode;
use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Http\CartHttp;
use App\Tenant\Cart\Http\Requests\EnterPromotionCodeRequest;
use App\Tenant\Cart\Http\Resources\CartResource;
use App\Tenant\Promotions\Exceptions\CodeNotValidException;
use App\Tenant\Promotions\Exceptions\TooManyCodeAttemptsException;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\Request;

/**
 * The promotion code on the shopper's cart: one at a time, alongside the store's automatic promotions.
 */
final class CartCodeController extends Controller
{
    public function __construct(
        private readonly PriceCart $priceCart,
    ) {}

    /**
     * Enter a promotion code.
     *
     * Replaces any code the cart had. Every code that can't be used now gets
     * the same answer, 422 code_not_valid, and the cart keeps its code. A
     * code that can be used is kept even while it doesn't apply to the cart
     * yet; the cart's code says why. Each cart (or signed-in customer) may
     * enter a few codes a minute (429 too_many_code_attempts), under a higher
     * ceiling per IP address.
     *
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     * @throws CodeNotValidException
     * @throws TooManyCodeAttemptsException
     */
    #[HeaderParameter(CartToken::HEADER, "A guest's cart token, from the response that created the cart. Ignored when signed in.", type: 'string')]
    #[HeaderParameter('If-Match', 'The cart version (ETag) this change was made from.', type: 'string', example: '"7"')]
    public function update(EnterPromotionCodeRequest $request, EnterPromotionCode $enterPromotionCode): CartResource
    {
        $cart = $enterPromotionCode->handle(CartHttp::shopper($request), $request->code(), CartHttp::expectedVersion($request));

        return new CartResource($this->priceCart->handle($cart));
    }

    /**
     * Remove the promotion code.
     *
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    #[HeaderParameter(CartToken::HEADER, "A guest's cart token, from the response that created the cart. Ignored when signed in.", type: 'string')]
    #[HeaderParameter('If-Match', 'The cart version (ETag) this change was made from.', type: 'string', example: '"7"')]
    public function destroy(Request $request, RemovePromotionCode $removePromotionCode): CartResource
    {
        $cart = $removePromotionCode->handle(CartHttp::shopper($request), CartHttp::expectedVersion($request));

        return new CartResource($this->priceCart->handle($cart));
    }
}
