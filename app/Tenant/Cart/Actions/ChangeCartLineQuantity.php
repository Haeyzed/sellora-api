<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Exceptions\VariantNotAvailableException;
use App\Tenant\Cart\Exceptions\VariantOutOfStockException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Cart\Services\CartPrices;
use App\Tenant\Cart\Services\CartVariants;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Sets how many of a line's variant the shopper wants, made from the cart version they were looking at.
 *
 * Asking for more is checked exactly like an add: the variant must still be
 * for sale and not out of stock (backorders are fine). Asking for fewer, or
 * the same, always works, even on a line that can't be bought any more, so
 * the shopper can bring it down; the cart keeps saying why it can't be
 * bought. Changing a line is looking at it again, so a price change it
 * showed is no longer flagged. Removing a line is its own request.
 */
final readonly class ChangeCartLineQuantity
{
    public function __construct(
        private FindCart $findCart,
        private CartChanges $cartChanges,
        private CartPrices $cartPrices,
        private CartVariants $cartVariants,
    ) {}

    /**
     * @param  int  $quantity  Already checked against the per-line limit.
     *
     * @throws CartNotFoundException
     * @throws ModelNotFoundException When the line isn't in the shopper's cart.
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     * @throws VariantNotAvailableException When more is asked of a variant no longer for sale.
     * @throws VariantOutOfStockException When more is asked of a variant out of stock that takes no backorders.
     */
    public function handle(Shopper $shopper, string $linePublicId, int $quantity, ?int $expectedVersion): Cart
    {
        $cartPrices = $this->cartPrices;
        $cartVariants = $this->cartVariants;

        return $this->cartChanges->apply($this->findCart->handle($shopper), $expectedVersion, true, static function (Cart $cart) use ($linePublicId, $quantity, $cartPrices, $cartVariants): void {
            $line = $cart->lines()->where('public_id', $linePublicId)->with('variant')->firstOrFail();
            $price = $cartPrices->unitPriceOf($cart, $line->variant);

            if ($quantity > $line->quantity) {
                $cartVariants->ensureCanBeAdded($line->variant);
            }

            $line->quantity = $quantity;

            if ($price !== null) {
                CartPrices::remember($line, $line->variant, $price);
            }

            $line->save();
        });
    }
}
