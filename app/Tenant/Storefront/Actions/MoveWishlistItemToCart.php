<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Actions;

use App\Tenant\Cart\Actions\AddCartLine;
use App\Tenant\Cart\Actions\CreateCart;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartFullException;
use App\Tenant\Cart\Exceptions\CartLineQuantityTooHighException;
use App\Tenant\Cart\Exceptions\VariantNotAvailableException;
use App\Tenant\Cart\Exceptions\VariantOutOfStockException;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Storefront\Models\WishlistItem;

/**
 * Puts a saved item in the customer's cart and takes it off their wishlist.
 *
 * The cart decides, through its own rules: the variant must be for sale and
 * not out of stock, and the cart's limits apply. Only once it is in the cart
 * is it removed from the wishlist, so a refusal leaves it saved.
 */
final readonly class MoveWishlistItemToCart
{
    public function __construct(
        private CreateCart $createCart,
        private AddCartLine $addCartLine,
    ) {}

    /**
     * @param  ProductVariant  $variant  The item's own variant, or the one the customer chose for an item saved without one (checked by the request).
     *
     * @throws VariantNotAvailableException
     * @throws VariantOutOfStockException
     * @throws CartFullException
     * @throws CartLineQuantityTooHighException
     */
    public function handle(WishlistItem $item, ProductVariant $variant, int $quantity): void
    {
        $shopper = Shopper::customer($item->customer);

        $this->createCart->handle($shopper);
        $this->addCartLine->handle($shopper, $variant, $quantity);

        $item->delete();
    }
}
