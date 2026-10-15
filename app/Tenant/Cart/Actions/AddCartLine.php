<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartFullException;
use App\Tenant\Cart\Exceptions\CartLineQuantityTooHighException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\VariantNotAvailableException;
use App\Tenant\Cart\Exceptions\VariantOutOfStockException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;
use App\Tenant\Cart\Services\CartPrices;
use App\Tenant\Cart\Services\CartVariants;
use App\Tenant\Catalog\Models\ProductVariant;

/**
 * Puts some of a variant in the shopper's cart, adding to its line when it is already there.
 *
 * Only a variant that is for sale and not out of stock (backorders are fine)
 * goes in; how many are left is never checked or said here, only at
 * checkout. A cart holds at most cart.max_lines different variants, and a
 * line at most cart.max_line_quantity, even when two adds arrive at once.
 */
final readonly class AddCartLine
{
    public function __construct(
        private FindCart $findCart,
        private CartVariants $cartVariants,
        private CartChanges $cartChanges,
        private CartPrices $cartPrices,
    ) {}

    /**
     * @throws CartNotFoundException
     * @throws VariantNotAvailableException
     * @throws VariantOutOfStockException
     * @throws CartFullException When it would be one variant too many.
     * @throws CartLineQuantityTooHighException When the line would ask for too many.
     */
    public function handle(Shopper $shopper, ProductVariant $variant, int $quantity): Cart
    {
        $cart = $this->findCart->handle($shopper);
        $this->cartVariants->ensureCanBeAdded($variant);
        $price = $this->cartPrices->unitPriceOf($cart, $variant) ?? throw new VariantNotAvailableException;

        return $this->cartChanges->apply($cart, null, false, static function (Cart $cart) use ($variant, $quantity, $price): void {
            $line = $cart->lines()->where('product_variant_id', $variant->id)->first();

            if ($line === null) {
                self::ensureRoomFor($cart);
                $line = $cart->lines()->make(['product_variant_id' => $variant->id, 'quantity' => 0]);
            }

            $line->quantity = self::withinLineLimit($line->quantity + $quantity);
            // Adding is looking at the line again: its price is as the shopper sees it now.
            CartPrices::remember($line, $variant, $price);
            $line->save();
        });
    }

    private static function ensureRoomFor(Cart $cart): void
    {
        $limit = config()->integer('cart.max_lines');

        if ($cart->lines()->count() >= $limit) {
            throw new CartFullException($limit);
        }
    }

    private static function withinLineLimit(int $quantity): int
    {
        $limit = config()->integer('cart.max_line_quantity');

        return $quantity <= $limit ? $quantity : throw new CartLineQuantityTooHighException($limit);
    }
}
