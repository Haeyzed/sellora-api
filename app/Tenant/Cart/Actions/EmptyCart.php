<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Actions;

use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartChangedException;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Exceptions\CartVersionRequiredException;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Services\CartChanges;

/**
 * Takes every line out of the shopper's cart, made from the cart version they were looking at. The cart itself stays.
 */
final readonly class EmptyCart
{
    public function __construct(
        private FindCart $findCart,
        private CartChanges $cartChanges,
    ) {}

    /**
     * @throws CartNotFoundException
     * @throws CartVersionRequiredException
     * @throws CartChangedException
     */
    public function handle(Shopper $shopper, ?int $expectedVersion): Cart
    {
        return $this->cartChanges->apply($this->findCart->handle($shopper), $expectedVersion, true, static function (Cart $cart): void {
            $cart->lines()->delete();
        });
    }
}
