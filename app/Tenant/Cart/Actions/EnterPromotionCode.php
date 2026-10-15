<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Promotions\Actions\FindPromotionCode;
use App\Tenant\Promotions\Exceptions\CodeNotValidException;
use App\Tenant\Promotions\Exceptions\TooManyCodeAttemptsException;

/**
 * Puts the promotion code a shopper typed on their cart, in place of any code it had (one code per cart), made from the cart version they were looking at.
 *
 * Any code that can't be used now gets the same answer (code_not_valid),
 * and the cart keeps its code. One that can be used is kept even while it
 * doesn't apply to the cart yet (a minimum not met, say): the cart says why,
 * and adding to the cart may make it apply. Each cart, or each signed-in
 * customer, may enter only so many codes a minute (the coupon_redemption
 * limit), under a higher ceiling per IP address on the route.
 */
final readonly class EnterPromotionCode
{
    public function __construct(
        private FindCart $findCart,
        private CartChanges $cartChanges,
        private FindPromotionCode $findPromotionCode,
    ) {}

    /**
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     * @throws TooManyCodeAttemptsException When this cart or customer has entered codes too often this minute.
     * @throws CodeNotValidException When the code can't be used now, for any reason.
     */
    public function handle(Shopper $shopper, string $code, ?int $expectedVersion): Cart
    {
        $cart = $this->findCart->handle($shopper);
        $requester = $shopper->customer === null ? 'cart:'.$cart->public_id : 'customer:'.$shopper->customer->public_id;
        $findPromotionCode = $this->findPromotionCode;

        return $this->cartChanges->apply($cart, $expectedVersion, true, static function (Cart $cart) use ($code, $requester, $findPromotionCode): void {
            $cart->promotion_code = $findPromotionCode->forShopper($code, $requester)->code;
        });
    }
}
