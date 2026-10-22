<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Tenant\Cart\Actions\FindCart;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Checkout\Enums\CheckoutStatus;
use App\Tenant\Checkout\Exceptions\CheckoutNotFoundException;
use App\Tenant\Checkout\Exceptions\GuestCheckoutNotAllowedException;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Settings\Actions\FindStoreSettings;

/**
 * Finds the shopper's open checkout: the one for their cart (a customer's own, or the guest cart their token opens). A guest finds none while the store doesn't allow guest checkout.
 */
final readonly class FindCheckout
{
    public function __construct(
        private FindCart $findCart,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @throws CheckoutNotFoundException When the shopper has no cart, or no open checkout for it.
     * @throws GuestCheckoutNotAllowedException When a guest checks out in a store that doesn't allow it.
     */
    public function handle(Shopper $shopper): Checkout
    {
        $this->ensureAllowed($shopper);

        try {
            $cart = $this->findCart->handle($shopper);
        } catch (CartNotFoundException) {
            throw new CheckoutNotFoundException;
        }

        return Checkout::query()->where('cart_id', $cart->id)->where('status', CheckoutStatus::Open->value)->first() ?? throw new CheckoutNotFoundException;
    }

    /**
     * One of the shopper's checkouts by its ID, open or already placed: what placing an order names, so pressing twice finds the order the first press made.
     *
     * @throws CheckoutNotFoundException When it isn't for the shopper's cart.
     * @throws GuestCheckoutNotAllowedException
     */
    public function byId(Shopper $shopper, string $publicId): Checkout
    {
        $this->ensureAllowed($shopper);

        try {
            $cart = $this->findCart->handle($shopper);
        } catch (CartNotFoundException) {
            throw new CheckoutNotFoundException;
        }

        return Checkout::query()->where('cart_id', $cart->id)->where('public_id', $publicId)->first() ?? throw new CheckoutNotFoundException;
    }

    /**
     * @throws GuestCheckoutNotAllowedException
     */
    public function ensureAllowed(Shopper $shopper): void
    {
        if ($shopper->customer === null && ! $this->findStoreSettings->handle()->guest_checkout) {
            throw new GuestCheckoutNotAllowedException;
        }
    }
}
