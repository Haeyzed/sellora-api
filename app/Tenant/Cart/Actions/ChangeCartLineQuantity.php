<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Cart\Services\CartPrices;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Sets how many of a line's variant the shopper wants, made from the cart version they were looking at.
 *
 * Works on a line whose variant has stopped being for sale too, so the
 * shopper can still bring it down; the cart keeps saying why it can't be
 * bought. Changing a line is looking at it again, so a price change it
 * showed is no longer flagged. Removing a line is its own request.
 */
final readonly class ChangeCartLineQuantity
{
    public function __construct(
        private FindCart $findCart,
        private CartChanges $cartChanges,
        private CartPrices $cartPrices,
    ) {}

    /**
     * @param  int  $quantity  Already checked against the per-line limit.
     *
     * @throws CartNotFoundException
     * @throws ModelNotFoundException When the line isn't in the shopper's cart.
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    public function handle(Shopper $shopper, string $linePublicId, int $quantity, ?int $expectedVersion): Cart
    {
        $cartPrices = $this->cartPrices;

        return $this->cartChanges->apply($this->findCart->handle($shopper), $expectedVersion, true, static function (Cart $cart) use ($linePublicId, $quantity, $cartPrices): void {
            $line = $cart->lines()->where('public_id', $linePublicId)->with('variant')->firstOrFail();
            $line->quantity = $quantity;
            $price = $cartPrices->unitPriceOf($cart, $line->variant);

            if ($price !== null) {
                CartPrices::remember($line, $line->variant, $price);
            }

            $line->save();
        });
    }
}
